<?php
// ============================================================
//  proxy.php  —  Consulta TODOS os modelos simultaneamente
//  Detecta /HF para gerar História Funcional Salesforce
// ============================================================
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/hf-prompt.php';
require_once __DIR__ . '/ata-prompt.php';
require_once __DIR__ . '/spec-prompt.php';
require_once __DIR__ . '/deploy-handler.php';

header('Content-Type: application/json');

iniciarSessao();
if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autenticado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || empty($body['messages']) || !is_array($body['messages'])) {
    http_response_code(400);
    echo json_encode(['erro' => 'Requisição inválida.']);
    exit;
}

$messages = $body['messages'];

// ── BUSCA NA BASE DE CONHECIMENTO ──────────────────────────────────────

function dividirEmBlocos(string $texto, int $tamanhoFallback): array {
    // Tenta dividir por headings markdown (##, ###, ####) — chunking semântico
    $blocos = preg_split('/\n(?=#{1,4}\s)/u', $texto);

    // Se não encontrou headings, faz fallback por linhas
    if (count($blocos) <= 1) {
        $linhas = explode("\n", $texto);
        $blocos = [];
        $atual  = [];
        foreach ($linhas as $linha) {
            $atual[] = $linha;
            if (count($atual) >= $tamanhoFallback) {
                $blocos[] = implode("\n", $atual);
                $atual = [];
            }
        }
        if (!empty($atual)) $blocos[] = implode("\n", $atual);
    }

    return $blocos;
}

function pontuarBloco(string $bloco, array $palavras): int {
    $blocoLower = mb_strtolower($bloco);
    $pontos = 0;

    // Primeira linha (heading) vale mais
    $primeiraLinha = mb_strtolower(strtok($bloco, "\n"));

    foreach ($palavras as $palavra) {
        if (mb_strlen($palavra) >= 3) {
            // Peso 3x se keyword aparece no heading
            if (str_contains($primeiraLinha, $palavra)) {
                $pontos += 3;
            }
            // Ocorrências no corpo
            $pontos += substr_count($blocoLower, $palavra);
        }
    }
    return $pontos;
}

function buscarBaseConhecimento(string $query, int $maxChunks = 0): string {
    $kbDir     = KB_DIR;
    $maxChunks = $maxChunks ?: KB_MAX_CHUNKS;
    $chunkSize = KB_CHUNK_SIZE;

    if (!is_dir($kbDir)) return '';

    $palavras = array_unique(array_filter(
        explode(' ', mb_strtolower(preg_replace('/[^\p{L}\p{N} ]/u', ' ', $query))),
        fn($p) => mb_strlen($p) >= 3
    ));
    if (empty($palavras)) return '';

    $resultados = [];
    $arquivos = array_merge(glob($kbDir . '/*.txt') ?: [], glob($kbDir . '/*.md') ?: []);

    foreach ($arquivos as $arquivo) {
        $conteudo = file_get_contents($arquivo);
        if (!$conteudo) continue;
        $nomeArquivo = basename($arquivo);
        foreach (dividirEmBlocos($conteudo, $chunkSize) as $bloco) {
            $bloco = trim($bloco);
            if (mb_strlen($bloco) < 80) continue; // ignora blocos muito curtos
            $pontos = pontuarBloco($bloco, $palavras);
            if ($pontos > 0) {
                $resultados[] = [
                    'pontos' => $pontos,
                    'fonte'  => $nomeArquivo,
                    'texto'  => mb_substr($bloco, 0, 2000), // limita tamanho do chunk
                ];
            }
        }
    }
    if (empty($resultados)) return '';

    usort($resultados, fn($a, $b) => $b['pontos'] - $a['pontos']);
    $melhores = array_slice($resultados, 0, $maxChunks);

    // Inclui nome do arquivo fonte em cada chunk
    $saida = [];
    foreach ($melhores as $r) {
        $saida[] = "[Fonte: {$r['fonte']}]\n{$r['texto']}";
    }
    return implode("\n\n---\n\n", $saida);
}

// ── DETECTA MODO (/HF, /ATA ou normal) ─────────────────────────────

$ultimaMensagem = '';
foreach (array_reverse($messages) as $msg) {
    if ($msg['role'] === 'user') { $ultimaMensagem = $msg['content']; break; }
}

$modoDeploy  = isDeployTrigger($ultimaMensagem);
$modoHF      = !$modoDeploy && isHFTrigger($ultimaMensagem);
$modoSpec    = !$modoDeploy && !$modoHF && isSpecTrigger($ultimaMensagem);
$modoAta     = !$modoDeploy && !$modoHF && !$modoSpec && isAtaTrigger($ultimaMensagem);
$necessidade  = $modoHF ? extrairNecessidade($ultimaMensagem) : '';
$conteudoSpec = $modoSpec ? extrairConteudoSpec($ultimaMensagem) : '';
$conteudoAta  = $modoAta ? extrairConteudoAta($ultimaMensagem) : '';

// ── MONTA O SYSTEM PROMPT ──────────────────────────────────────────────

// ── MODO DEPLOY (chama MCP Server direto, sem IA) ────────────────────
if ($modoDeploy) {
    $resultado = processarDeploy($ultimaMensagem, $messages);
    if ($resultado) {
        echo json_encode($resultado);
        exit;
    }
}

if ($modoAta) {
    // Modo ATA
    $systemPrompt = getAtaPrompt();
    $maxTokens    = 8192;
    $temperature  = 0.3;

    // Se o usuário mandou só "/ata" sem conteúdo, pedir a transcrição
    if (empty($conteudoAta)) {
        echo json_encode([
            'choices' => [['message' => ['content' =>
                "Para gerar a **Ata de Reunião**, cole a transcrição ou anotações da reunião.\n\n" .
                "Pode ser:\n" .
                "- Transcrição completa da reunião\n" .
                "- Anotações em tópicos\n" .
                "- Resumo dos pontos discutidos\n\n" .
                "Exemplo: `/ata Reunião sobre modelagem de produtos B2B no Salesforce. Participantes: João, Maria...`\n\n" .
                "Cole o conteúdo e eu estruturo na ata formatada."
            ]]],
            'modelo_usado' => 'system',
            'modelo_label' => 'Sistema',
            'tipo'         => 'info',
        ]);
        exit;
    }

    // Formata a mensagem para o modelo
    $msgFormatada = "Gere uma Ata de Reunião completa e profissional a partir da seguinte transcrição/anotações:\n\n" . $conteudoAta;
    foreach ($messages as &$m) {
        if ($m === end($messages) && $m['role'] === 'user') {
            $m['content'] = $msgFormatada;
        }
    }
    unset($m);

} elseif ($modoHF) {
    // Modo HF: busca mais contexto da KB (5 chunks) e usa prompt especializado
    $termosBusca = !empty($necessidade) ? $necessidade : $ultimaMensagem;
    $contextoKB  = buscarBaseConhecimento($termosBusca, 5);
    $systemPrompt = getHFPrompt($contextoKB);
    $maxTokens    = 4096;
    $temperature  = 0.4; // mais focado para documentos

    // Se o usuário mandou só "/hf" sem descrição, pedir a necessidade
    if (empty($necessidade)) {
        echo json_encode([
            'choices' => [['message' => ['content' =>
                "Para gerar a **História Funcional**, descreva a necessidade de negócio.\n\n" .
                "Exemplo: `/hf Criar processo de aprovação de descontos acima de 15% para oportunidades no Sales Cloud`\n\n" .
                "Pode ser uma frase curta ou um requisito detalhado — eu estruturo nas 14 seções."
            ]]],
            'modelo_usado' => 'system',
            'modelo_label' => 'Sistema',
            'tipo'         => 'info',
        ]);
        exit;
    }

    // Substitui a última mensagem do usuário pela necessidade formatada
    $msgFormatada = "Gere uma História Funcional Salesforce completa (14 seções) para a seguinte necessidade de negócio:\n\n" . $necessidade;
    foreach ($messages as &$m) {
        if ($m === end($messages) && $m['role'] === 'user') {
            $m['content'] = $msgFormatada;
        }
    }
    unset($m);

} elseif ($modoSpec) {
    // Modo SPEC: busca contexto da KB e usa prompt de spec técnica
    $termosBusca = !empty($conteudoSpec) ? $conteudoSpec : $ultimaMensagem;
    $contextoKB  = buscarBaseConhecimento($termosBusca, 5);
    $systemPrompt = getSpecPrompt($contextoKB);
    $maxTokens    = 16384;
    $temperature  = 0.2;

    if (empty($conteudoSpec)) {
        echo json_encode([
            'choices' => [['message' => ['content' =>
                "Para gerar a **Especificação Técnica**, forneça a história funcional ou requisito.\n\n" .
                "Exemplos:\n" .
                "- `/spec` + cole a história funcional gerada pelo `/hf`\n" .
                "- `/spec Criar módulo de visitas com check-in geolocalizado`\n\n" .
                "Pode ser um requisito curto ou uma HF completa — eu gero as 18 seções (com Runbook)."
            ]]],
            'modelo_usado' => 'system',
            'modelo_label' => 'Sistema',
            'tipo'         => 'info',
        ]);
        exit;
    }

    // Envolve o conteúdo em delimitadores para o modelo não copiar o formato
    $msgFormatada = <<<SPECMSG
TAREFA: Transformar o DOCUMENTO DE ENTRADA abaixo em uma ESPECIFICAÇÃO TÉCNICA (solution design).

REGRAS CRÍTICAS:
1. O documento abaixo entre === é APENAS material de referência — NÃO copie seu formato
2. Seu output DEVE ser uma ESPECIFICAÇÃO TÉCNICA com as 18 seções técnicas
3. COMECE com "# ESPECIFICAÇÃO TÉCNICA" — NUNCA com "# HISTÓRIA FUNCIONAL"
4. Foque em: Data Model (campos com API Names), Automações (OOTB→Flow→Apex), Validation Rules (com fórmulas), Security, Deploy, e RUNBOOK DE IMPLEMENTAÇÃO (seção 18)
5. NÃO repita o formato do documento de entrada
6. NÃO gere User Stories no formato "Como [persona], eu quero..."
7. A seção 18 (Runbook) deve ter instruções passo a passo com caminhos exatos no Setup

=== INÍCIO DO DOCUMENTO DE ENTRADA (use como base, NÃO copie o formato) ===

{$conteudoSpec}

=== FIM DO DOCUMENTO DE ENTRADA ===

Agora gere a ESPECIFICAÇÃO TÉCNICA completa com as 18 seções.
SPECMSG;

    foreach ($messages as &$m) {
        if ($m === end($messages) && $m['role'] === 'user') {
            $m['content'] = $msgFormatada;
        }
    }
    unset($m);

} else {
    // Modo normal — com data e anti-alucinação
    $contextoKB = buscarBaseConhecimento($ultimaMensagem);

    $dataAtual = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))
        ->format('l, d \d\e F \d\e Y, H:i');
    $systemPrompt = "Você é um assistente especializado e prestativo. Responda sempre em português do Brasil, de forma clara, objetiva e bem estruturada.\n"
        . "Data e hora atual: {$dataAtual} (horário de Brasília).";

    if (!empty($contextoKB)) {
        $systemPrompt .= "\n\n=== BASE DE CONHECIMENTO ===\n"
            . "REGRAS OBRIGATÓRIAS:\n"
            . "1. Use EXCLUSIVAMENTE as informações abaixo para responder sobre temas cobertos pela base.\n"
            . "2. Se a resposta NÃO estiver na base, diga: \"Essa informação não consta na base de conhecimento.\"\n"
            . "3. NÃO invente, NÃO infira, NÃO complete com informações que não estejam explicitamente nos trechos abaixo.\n"
            . "4. Cite a fonte entre colchetes [Fonte: nome_do_arquivo] quando usar informações da base.\n\n"
            . $contextoKB
            . "\n=== FIM DA BASE DE CONHECIMENTO ===";
    }

    $maxTokens   = 2048;
    $temperature = 0.7;
}

$messagesComSystem = array_merge(
    [['role' => 'system', 'content' => $systemPrompt]],
    $messages
);

// ── CONSULTA TODOS OS MODELOS SIMULTANEAMENTE ──────────────────────────

$multiCurl = curl_multi_init();
$handles   = [];
$idx       = 0;

// Handles para OpenRouter
foreach (array_keys(MODELOS_OPENROUTER) as $modelo) {
    $payload = json_encode([
        'model'       => $modelo,
        'messages'    => $messagesComSystem,
        'max_tokens'  => $maxTokens,
        'temperature' => $temperature,
    ]);

    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENROUTER_KEY,
            'HTTP-Referer: ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
            'X-Title: AI Chat',
        ],
        CURLOPT_TIMEOUT => 60,
    ]);

    curl_multi_add_handle($multiCurl, $ch);
    $handles[$idx] = ['curl' => $ch, 'modelo' => $modelo, 'label' => MODELOS_OPENROUTER[$modelo]];
    $idx++;
}

// Handles para xAI / Grok (se a chave estiver configurada)
if (defined('GROK_KEY') && GROK_KEY && !str_contains(GROK_KEY, 'COLOQUE')) {
    foreach (array_keys(MODELOS_GROK) as $modelo) {
        $payload = json_encode([
            'model'       => $modelo,
            'messages'    => $messagesComSystem,
            'max_tokens'  => $maxTokens,
            'temperature' => $temperature,
        ]);

        $ch = curl_init('https://api.x.ai/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . GROK_KEY,
            ],
            CURLOPT_TIMEOUT => 60,
        ]);

        curl_multi_add_handle($multiCurl, $ch);
        $handles[$idx] = ['curl' => $ch, 'modelo' => $modelo, 'label' => MODELOS_GROK[$modelo]];
        $idx++;
    }
}

$running   = null;
$resultado = null;

do {
    curl_multi_exec($multiCurl, $running);

    while ($info = curl_multi_info_read($multiCurl)) {
        if ($info['msg'] === CURLMSG_DONE) {
            $ch = $info['handle'];

            $modeloUsado = '';
            $modeloLabel = '';
            foreach ($handles as $h) {
                if ($h['curl'] === $ch) {
                    $modeloUsado = $h['modelo'];
                    $modeloLabel = $h['label'];
                    break;
                }
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $resposta = curl_multi_getcontent($ch);
            $dados    = json_decode($resposta, true);

            if ($httpCode === 200 && $dados && !isset($dados['error']) && isset($dados['choices'][0]['message']['content'])) {
                $dados['modelo_usado'] = $modeloUsado;
                $dados['modelo_label'] = $modeloLabel;
                if ($modoHF) $dados['tipo'] = 'hf';
                if ($modoSpec) $dados['tipo'] = 'spec';
                if ($modoAta) $dados['tipo'] = 'ata';
                $resultado = $dados;
                break 2;
            }
        }
    }

    if ($running > 0) curl_multi_select($multiCurl, 0.1);

} while ($running > 0);

foreach ($handles as $h) {
    curl_multi_remove_handle($multiCurl, $h['curl']);
    curl_close($h['curl']);
}
curl_multi_close($multiCurl);

// ── RETORNA A RESPOSTA ─────────────────────────────────────────────────

if ($resultado) {
    echo json_encode($resultado);
} else {
    http_response_code(503);
    echo json_encode([
        'erro' => 'Nenhum modelo conseguiu responder no momento. Tente novamente em alguns segundos.'
    ]);
}
