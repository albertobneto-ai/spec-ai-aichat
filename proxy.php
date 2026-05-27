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

// ── HELPER: LIMPA MARKDOWN ─────────────────────────────────────────────

function limparMarkdown(string $texto): string {
    // Remove headers markdown (# ## ### ####)
    $texto = preg_replace('/^#{1,4}\s+/m', '', $texto);
    // Remove bold/italic
    $texto = preg_replace('/\*{1,3}([^*]+)\*{1,3}/', '$1', $texto);
    // Remove tabelas markdown (linhas com |)
    $texto = preg_replace('/^\|[-\s|:]+\|$/m', '', $texto);
    // Simplifica linhas de tabela: | val1 | val2 | → val1: val2
    $texto = preg_replace_callback('/^\|(.+)\|$/m', function($m) {
        $cells = array_map('trim', explode('|', trim($m[1], '| ')));
        $cells = array_filter($cells);
        return implode(': ', $cells);
    }, $texto);
    // Remove checkboxes
    $texto = str_replace(['☐ ', '☑ ', '✓ '], '', $texto);
    // Remove linhas vazias consecutivas
    $texto = preg_replace('/\n{3,}/', "\n\n", $texto);
    // Remove "HISTÓRIA FUNCIONAL" do título
    $texto = preg_replace('/^HISTÓRIA FUNCIONAL\s*/mi', '', $texto);
    $texto = preg_replace('/^Historia_Funcional\s*/mi', '', $texto);

    return trim($texto);
}

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
// IMPORTANTE: /spec ANTES de /hf — porque a HF colada contém "história funcional"
// que ativaria isHFTrigger via str_contains se checada primeiro
$modoSpec    = !$modoDeploy && isSpecTrigger($ultimaMensagem);
$modoHF      = !$modoDeploy && !$modoSpec && isHFTrigger($ultimaMensagem);
$modoAta     = !$modoDeploy && !$modoSpec && !$modoHF && isAtaTrigger($ultimaMensagem);
$necessidade  = $modoHF ? extrairNecessidade($ultimaMensagem) : '';
$conteudoSpec = $modoSpec ? extrairConteudoSpec($ultimaMensagem) : '';
$conteudoAta  = $modoAta ? extrairConteudoAta($ultimaMensagem) : '';
$apenasGrok   = false;
$modeloForcar = null;
$modeloForcarLabel = null;
$modelosSpec  = []; // Modelos extras para /spec (Qwen3 + DeepSeek)

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

    // Strip markdown do conteúdo para o modelo não copiar o formato
    $conteudoLimpo = !empty($conteudoSpec) ? limparMarkdown($conteudoSpec) : '';

    // Injeta o conteúdo da HF no SYSTEM PROMPT (não no user message)
    $systemPrompt = getSpecPrompt($contextoKB);
    if (!empty($conteudoLimpo)) {
        $systemPrompt .= "\n\n=== REQUISITO FUNCIONAL DE ENTRADA (texto plano — transforme em spec técnica) ===\n"
            . $conteudoLimpo
            . "\n=== FIM DO REQUISITO ===\n"
            . "\nAgora gere a ESPECIFICAÇÃO TÉCNICA com as 18 seções. Comece com '# ESPECIFICAÇÃO TÉCNICA'.";
    }

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

    // User message é curta e direta — SEM o conteúdo da HF
    $msgFormatada = "Gere agora a ESPECIFICAÇÃO TÉCNICA completa (18 seções) com base no requisito funcional que está no system prompt. Comece com '# ESPECIFICAÇÃO TÉCNICA'. Inclua Data Model com API Names, Validation Rules com fórmulas, e Runbook de Implementação na seção 18.";

    // IMPORTANTE: Para /spec, LIMPA o histórico da conversa
    // Se não limpar, o modelo vê HFs anteriores e copia o formato
    $messages = [['role' => 'user', 'content' => $msgFormatada]];

    // /spec usa Claude Sonnet — maior precisão técnica em Salesforce
    $modelosSpec = [];
    $apenasGrok  = true;
    $usarClaude  = true;

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

// ── MODO SPEC: Claude Sonnet (Anthropic API) ──────────────────────────
if (!empty($usarClaude) && defined('ANTHROPIC_KEY') && ANTHROPIC_KEY) {
    $payload = json_encode([
        'model'      => 'claude-sonnet-4-6',
        'max_tokens' => $maxTokens,
        'system'     => $systemPrompt,
        'messages'   => $messages,
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . ANTHROPIC_KEY,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_TIMEOUT => 180,
    ]);

    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $dados = json_decode($resposta, true);

    if ($httpCode === 200 && isset($dados['content'][0]['text'])) {
        echo json_encode([
            'choices'      => [['message' => ['content' => $dados['content'][0]['text']]]],
            'modelo_usado' => 'claude-sonnet-4-6',
            'modelo_label' => 'Claude Sonnet 4',
            'tipo'         => 'spec',
        ]);
    } else {
        http_response_code(503);
        echo json_encode(['erro' => 'Claude Sonnet não respondeu. Tente novamente.']);
    }
    exit;
}

// ── CONSULTA TODOS OS MODELOS SIMULTANEAMENTE ──────────────────────────

$multiCurl = curl_multi_init();
$handles   = [];
$idx       = 0;

// Determina quais modelos OpenRouter usar
$orModelos = MODELOS_OPENROUTER;
if (!empty($modelosSpec)) {
    $orModelos = array_merge($modelosSpec, MODELOS_OPENROUTER);
}

// Handles para OpenRouter (pula se apenasGrok = true)
if (!$apenasGrok) {
foreach ($orModelos as $modelo => $label) {
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
            'X-Title: Spec AI',
        ],
        CURLOPT_TIMEOUT => 120,
    ]);

    curl_multi_add_handle($multiCurl, $ch);
    $handles[$idx] = ['curl' => $ch, 'modelo' => $modelo, 'label' => $label];
    $idx++;
}
}

// Handles para xAI / Grok
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
            CURLOPT_TIMEOUT => 120,
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
