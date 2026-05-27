<?php
// ============================================================
//  deploy-handler.php  —  Integração com MCP Salesforce Server
//  Trigger: /deploy, /describe, /status, /scratch
// ============================================================

require_once __DIR__ . '/alias-map.php';

define('MCP_SERVER', 'https://mcp-sf-provisioning-462dd29c2455.herokuapp.com');

/**
 * Detecta se a mensagem é um trigger de deploy/salesforce
 */
function isDeployTrigger(string $mensagem): bool {
    $mensagem = mb_strtolower(trim($mensagem));
    $triggers = ['/deploy', '/describe', '/status', '/scratch', '/mock', '/help', '/comandos'];
    foreach ($triggers as $t) {
        if (str_starts_with($mensagem, $t)) return true;
    }
    $frases = [
        'deployar na org', 'provisionar metadados', 'deploy manifest',
        'criar na org', 'deploy workstream', 'provisionar spec',
    ];
    foreach ($frases as $f) {
        if (str_contains($mensagem, $f)) return true;
    }
    return false;
}

/**
 * Processa comandos de deploy/salesforce
 * Retorna array com resposta formatada ou null se não processou
 */
function processarDeploy(string $mensagem, array $historico): ?array {
    $msg = trim($mensagem);
    $msgLower = mb_strtolower($msg);

    // ── /help ou /comandos ──
    if (str_starts_with($msgLower, '/help') || str_starts_with($msgLower, '/comandos')) {
        return respostaDeploy(
            "## Comandos do Spec AI\n\n" .
            "---\n\n" .
            "### 📝 Geração de Documentos (via IA)\n\n" .
            "| Comando | O que faz |\n|---|---|\n" .
            "| `/hf` | **História Funcional** — Gera documento com 14 seções a partir de uma necessidade de negócio. Download automático em .docx |\n" .
            "| `/spec` | **Especificação Técnica** — Gera spec com 18 seções (inclui Runbook de implementação) a partir de uma HF ou requisito. Download automático em .docx |\n" .
            "| `/ata` | **Ata de Reunião** — Transforma transcrição ou anotações em ata profissional com 11 seções. Download automático em .docx |\n\n" .
            "**Como usar:**\n\n" .
            "- `/hf Criar módulo de visitas com check-in geolocalizado no Sales Cloud`\n" .
            "- `/spec` + cole a história funcional gerada pelo `/hf`\n" .
            "- `/ata Reunião sobre modelagem de produtos B2B. Participantes: João, Maria...`\n\n" .
            "**Fluxo encadeado:** `/hf` → gera HF → `/spec` + cola a HF → gera Spec com Runbook → `/deploy` + manifest JSON → org configurada\n\n" .
            "---\n\n" .
            "### ☁️ Salesforce (MCP Server direto)\n\n" .
            "| Comando | O que faz |\n|---|---|\n" .
            "| `/deploy {JSON}` | Deploya manifest JSON na org Salesforce |\n" .
            "| `/status` | Verifica conexão com a org (Org ID, username, URL) |\n" .
            "| `/describe conta` | Consulta objeto — aceita PT-BR: conta, oportunidade, lead, caso, pedido, cotação... |\n" .
            "| `/scratch list` | Lista scratch orgs ativas |\n" .
            "| `/scratch create leads` | Cria scratch org por workstream |\n" .
            "| `/scratch delete {orgId}` | Deleta scratch org |\n" .
            "| `/mock leads-b2b` | Insere dados de teste na org |\n\n" .
            "---\n\n" .
            "### 💬 Chat livre\n\n" .
            "Qualquer mensagem sem comando especial vai para a IA (Grok / OpenRouter) com consulta automática à base de conhecimento local.\n\n" .
            "---\n\n" .
            "| `/help` | Exibe esta ajuda |",
            'info'
        );
    }

    // ── /status ──
    if (str_starts_with($msgLower, '/status')) {
        return chamarMCP('/test-connection', 'GET');
    }

    // ── /describe {objeto} ──
    if (str_starts_with($msgLower, '/describe')) {
        $objeto = trim(preg_replace('/^\/describe\s*/i', '', $msg));
        if (empty($objeto)) {
            return respostaDeploy(
                "Para consultar um objeto, informe o nome (aceita PT-BR).\n\n" .
                "Exemplos:\n" .
                "- `/describe conta` ou `/describe Account`\n" .
                "- `/describe oportunidade` ou `/describe Opportunity`\n" .
                "- `/describe lead` · `/describe contato` · `/describe caso`\n" .
                "- `/describe pedido` · `/describe cotação` · `/describe produto`\n" .
                "- `/describe Custom_Object__c`",
                'info'
            );
        }
        $resolved = resolverObjeto($objeto);
        $apiName  = $resolved['apiName'];
        $nota     = $resolved['resolved']
            ? "\n\n> 🔄 `{$resolved['original']}` → `{$apiName}`"
            : '';

        $resultado = chamarMCP("/api/describe/" . urlencode($apiName), 'GET');

        // Injeta a nota de resolução no início da resposta
        if ($nota && isset($resultado['choices'][0]['message']['content'])) {
            $resultado['choices'][0]['message']['content'] =
                $nota . "\n\n" . $resultado['choices'][0]['message']['content'];
        }

        return $resultado;
    }

    // ── /scratch ──
    if (str_starts_with($msgLower, '/scratch')) {
        $args = trim(preg_replace('/^\/scratch\s*/i', '', $msg));
        $argsLower = mb_strtolower($args);

        if (empty($args) || $argsLower === 'list') {
            return chamarMCP('/api/scratch-orgs', 'GET');
        }
        if (str_starts_with($argsLower, 'create ')) {
            $template = trim(substr($args, 7));
            return chamarMCP('/api/scratch-orgs/create/' . urlencode($template), 'GET');
        }
        if (str_starts_with($argsLower, 'delete ')) {
            $orgId = trim(substr($args, 7));
            return chamarMCP('/api/scratch-orgs/delete/' . urlencode($orgId), 'GET');
        }
        if (str_starts_with($argsLower, 'login ')) {
            $id = trim(substr($args, 6));
            return chamarMCP('/api/scratch-orgs/login/' . urlencode($id), 'GET');
        }

        return respostaDeploy(
            "Comandos `/scratch` disponíveis:\n\n" .
            "- `/scratch list` — Listar orgs ativas\n" .
            "- `/scratch create {template}` — Criar scratch org\n" .
            "- `/scratch delete {orgId}` — Deletar scratch org\n" .
            "- `/scratch login {id}` — Obter link de login",
            'info'
        );
    }

    // ── /mock ──
    if (str_starts_with($msgLower, '/mock')) {
        $args = trim(preg_replace('/^\/mock\s*/i', '', $msg));
        if (empty($args)) {
            return respostaDeploy(
                "Para inserir dados de teste, informe o cenário.\n\n" .
                "Exemplos:\n" .
                "- `/mock leads-b2b` — Leads B2B com dados Neoway\n" .
                "- `/mock accounts` — Accounts com hierarquia\n" .
                "- `/mock full-cycle` — Ciclo completo Lead→Opp→Quote→Order",
                'info'
            );
        }
        return chamarMCP('/api/mock-data-b64/' . base64url_encode(json_encode(['scenario' => $args])), 'GET');
    }

    // ── /deploy ──
    if (str_starts_with($msgLower, '/deploy')) {
        $conteudo = trim(preg_replace('/^\/deploy\s*/i', '', $msg));

        if (empty($conteudo)) {
            // Verifica se tem spec/HF recente no histórico
            $ultimaResposta = '';
            foreach (array_reverse($historico) as $h) {
                if ($h['role'] === 'assistant') {
                    $ultimaResposta = $h['content'];
                    break;
                }
            }

            if (!empty($ultimaResposta) && (
                str_contains($ultimaResposta, '## 04. Data Model') ||
                str_contains($ultimaResposta, '## 04.') ||
                str_contains($ultimaResposta, 'API Name') ||
                str_contains($ultimaResposta, '__c')
            )) {
                // Envia a spec para o Grok gerar o manifest JSON automaticamente
                $manifest = gerarManifestViaIA($ultimaResposta);
                if ($manifest) {
                    // Manifest gerado — faz o deploy
                    $b64 = base64url_encode($manifest);
                    $resultado = chamarMCP('/api/deploy-b64/' . $b64, 'GET');
                    // Injeta info sobre o manifest gerado
                    if (isset($resultado['choices'][0]['message']['content'])) {
                        $resultado['choices'][0]['message']['content'] =
                            "🤖 Manifest gerado automaticamente pela IA a partir da spec.\n\n"
                            . $resultado['choices'][0]['message']['content']
                            . "\n\n<details><summary>📋 Manifest JSON usado</summary>\n\n```json\n"
                            . json_encode(json_decode($manifest, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                            . "\n```\n</details>";
                    }
                    return $resultado;
                } else {
                    return respostaDeploy(
                        "❌ Não consegui gerar o manifest automaticamente a partir da spec.\n\n" .
                        "Cole o manifest JSON manualmente:\n" .
                        "`/deploy {\"specName\":\"...\", \"metadata\":{...}}`\n\n" .
                        "Ou gere o manifest no Claude (claude.ai) com `/deploy` + a spec.",
                        'error'
                    );
                }
            }

            return respostaDeploy(
                "Para deployar metadados na org Salesforce, informe:\n\n" .
                "- `/deploy {manifest JSON}` — Deploy direto\n" .
                "- `/deploy` após gerar uma `/spec` — Usa a spec da conversa\n\n" .
                "Ou use os outros comandos:\n" .
                "- `/status` — Verificar conexão com a org\n" .
                "- `/describe Account` — Consultar objeto\n" .
                "- `/scratch list` — Gerenciar scratch orgs",
                'info'
            );
        }

        // Tenta parsear como JSON
        $jsonData = json_decode($conteudo, true);
        if ($jsonData && isset($jsonData['metadata'])) {
            // É um manifest válido — faz deploy
            $b64 = base64url_encode($conteudo);
            return chamarMCP('/api/deploy-b64/' . $b64, 'GET');
        }

        // Não é JSON — instruir o usuário
        return respostaDeploy(
            "O conteúdo não é um manifest JSON válido.\n\n" .
            "Para deployar, o formato deve ser:\n```json\n{\n  \"specName\": \"Nome\",\n  \"metadata\": {\n    \"customFields\": [\n      {\n        \"object\": \"Lead\",\n        \"fullName\": \"Lead.Campo__c\",\n        \"label\": \"Campo\",\n        \"type\": \"Text\",\n        \"length\": 100\n      }\n    ]\n  }\n}\n```\n\n" .
            "Gere o manifest no Claude (claude.ai) com o comando `/deploy` + a spec, e cole aqui o JSON resultante.",
            'info'
        );
    }

    return null;
}

/**
 * Chama o MCP Server no Heroku
 */
function chamarMCP(string $endpoint, string $method = 'GET', $body = null): array {
    $url = MCP_SERVER . $endpoint;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);

    if ($method === 'POST' && $body) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
    }

    $resp     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return respostaDeploy("❌ Erro de conexão com o servidor MCP:\n`$curlErr`\n\nVerifique se o servidor está ativo: " . MCP_SERVER . "/test-connection", 'error');
    }

    $dados = json_decode($resp, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        $formatado = formatarRespostaMCP($dados, $httpCode);
        return respostaDeploy($formatado, 'deploy');
    } else {
        $erro = $dados['message'] ?? $dados['error'] ?? $resp;
        return respostaDeploy("❌ Erro do servidor (HTTP $httpCode):\n`$erro`", 'error');
    }
}

/**
 * Formata a resposta do MCP Server para exibição
 */
function formatarRespostaMCP($dados, int $httpCode): string {
    if (!is_array($dados)) return "```json\n" . json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n```";

    // Status/Connection
    if (isset($dados['status']) && $dados['status'] === 'connected') {
        return "✅ **Conexão ativa**\n\n" .
            "| Campo | Valor |\n|---|---|\n" .
            "| Org ID | `{$dados['orgId']}` |\n" .
            "| Username | `{$dados['username']}` |\n" .
            "| Display Name | {$dados['displayName']} |";
    }

    // Deploy result
    if (isset($dados['success'])) {
        if ($dados['success']) {
            $componentes = $dados['components'] ?? $dados['details'] ?? [];
            $total = is_array($componentes) ? count($componentes) : ($dados['componentCount'] ?? '?');
            $msg = "✅ **Deploy realizado com sucesso!**\n\n" .
                "- Componentes: **$total**\n" .
                "- Spec: `" . ($dados['specName'] ?? 'N/A') . "`\n";
            if (is_array($componentes) && count($componentes) > 0) {
                $msg .= "\n| # | Componente | Tipo | Status |\n|---|---|---|---|\n";
                foreach (array_slice($componentes, 0, 30) as $i => $c) {
                    $nome = is_array($c) ? ($c['fullName'] ?? $c['name'] ?? '?') : $c;
                    $tipo = is_array($c) ? ($c['type'] ?? '-') : '-';
                    $msg .= "| " . ($i+1) . " | `$nome` | $tipo | ✅ |\n";
                }
            }
            return $msg;
        } else {
            $erros = $dados['errors'] ?? $dados['componentFailures'] ?? [];
            $msg = "❌ **Deploy falhou**\n\n";
            if (is_array($erros)) {
                foreach ($erros as $e) {
                    $msg .= "- " . (is_array($e) ? ($e['problem'] ?? json_encode($e)) : $e) . "\n";
                }
            }
            return $msg;
        }
    }

    // Describe result
    if (isset($dados['fields']) || isset($dados['name'])) {
        $nome = $dados['name'] ?? '?';
        $label = $dados['label'] ?? '';
        $fields = $dados['fields'] ?? [];
        $totalFields = count($fields);
        $customCount = count(array_filter($fields, fn($f) => !empty($f['custom'])));
        $standardCount = $totalFields - $customCount;

        $msg = "📋 **$nome** ($label) — $totalFields campos ($standardCount standard, $customCount custom)\n\n";

        if (!empty($fields)) {
            $msg .= "| # | Label (Org) | API Name | Tipo | Custom | Obrigatório |\n|---|---|---|---|---|---|\n";
            foreach ($fields as $i => $f) {
                $req = empty($f['nillable']) ? '✓' : '';
                $custom = !empty($f['custom']) ? '✦' : '';
                $msg .= "| " . ($i+1) . " | {$f['label']} | `{$f['name']}` | {$f['type']} | $custom | $req |\n";
            }
        }

        // Record Types
        if (!empty($dados['recordTypes'])) {
            $msg .= "\n**Record Types:**\n";
            foreach ($dados['recordTypes'] as $rt) {
                $status = !empty($rt['active']) ? '✅' : '⭕';
                $msg .= "- $status {$rt['name']}\n";
            }
        }

        // Org Info
        $orgInfo = buscarInfoOrg();
        if ($orgInfo) {
            $msg .= "\n---\n**Org conectada:**\n\n";
            $msg .= "| Campo | Valor |\n|---|---|\n";
            $msg .= "| Org ID | `{$orgInfo['orgId']}` |\n";
            $msg .= "| Username | `{$orgInfo['username']}` |\n";
            $msg .= "| Display Name | {$orgInfo['displayName']} |\n";
            $msg .= "| URL | `{$orgInfo['loginUrl']}` |\n";
        }

        return $msg;
    }

    // Scratch orgs list
    if (isset($dados['scratchOrgs']) || (is_array($dados) && isset($dados[0]['ScratchOrg']))) {
        $orgs = $dados['scratchOrgs'] ?? $dados;
        if (empty($orgs)) return "📋 **Scratch Orgs:** nenhuma ativa no momento.";
        $msg = "📋 **Scratch Orgs ativas**\n\n| # | ID | Status | Expira |\n|---|---|---|---|\n";
        foreach ($orgs as $i => $o) {
            $id = $o['ScratchOrg'] ?? $o['id'] ?? '?';
            $status = $o['Status'] ?? $o['status'] ?? '?';
            $expira = $o['ExpirationDate'] ?? $o['expirationDate'] ?? '?';
            $msg .= "| " . ($i+1) . " | `$id` | $status | $expira |\n";
        }
        return $msg;
    }

    // Fallback: JSON formatado
    return "```json\n" . json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n```";
}

/**
 * Monta a resposta no formato esperado pelo proxy.php
 */
function respostaDeploy(string $conteudo, string $tipo = 'deploy'): array {
    return [
        'choices' => [['message' => ['content' => $conteudo]]],
        'modelo_usado' => 'mcp-server',
        'modelo_label' => 'Salesforce MCP',
        'tipo'         => $tipo,
    ];
}

/**
 * Chama o Grok para extrair manifest JSON de uma spec/HF
 */
function gerarManifestViaIA(string $specContent): ?string {
    if (!defined('GROK_KEY') || !GROK_KEY) return null;

    $prompt = <<<PROMPT
Você é um extrator de metadados Salesforce. Analise o documento abaixo e gere APENAS um JSON de manifest no formato exato especificado. NÃO inclua texto, explicações ou markdown — APENAS o JSON puro.

FORMATO DO JSON (siga EXATAMENTE esta estrutura):
{
  "specName": "Nome_Descritivo_Da_Spec",
  "metadata": {
    "customObjects": [],
    "customFields": [
      {
        "object": "NomeDoObjeto",
        "fullName": "NomeDoObjeto.Nome_Campo__c",
        "label": "Label do Campo",
        "type": "Text",
        "length": 100
      }
    ],
    "validationRules": [
      {
        "object": "NomeDoObjeto",
        "fullName": "NomeDoObjeto.Nome_Rule",
        "active": true,
        "errorConditionFormula": "ISBLANK(Campo__c)",
        "errorMessage": "Mensagem de erro"
      }
    ],
    "recordTypes": [
      {
        "object": "NomeDoObjeto",
        "fullName": "NomeDoObjeto.Nome_RT",
        "label": "Nome do Record Type",
        "active": true
      }
    ]
  }
}

REGRAS:
- Tipos de campo válidos: Text, Number, Currency, Percent, Date, DateTime, Checkbox, Picklist, MultiselectPicklist, TextArea, LongTextArea, Lookup, Email, Phone, Url
- Para Text: inclua "length" (1-255)
- Para Number/Currency/Percent: inclua "precision" (até 18) e "scale" (casas decimais)
- Para Picklist: inclua "picklistValues": [{"fullName":"Valor1","default":false}]
- Para Lookup: inclua "referenceTo":"ObjetoAlvo" e "relationshipLabel":"Label"
- Para LongTextArea: inclua "length":32768 e "visibleLines":4
- Se um campo é padrão do Salesforce (Name, Email, Phone, etc), NÃO inclua — só campos CUSTOM (__c)
- Se a spec menciona mapeamento de campos padrão (ex: Lead Field Mapping), NÃO crie campos — são configurações OOTB
- fullName SEMPRE no formato "Objeto.Campo__c"
- Se não houver campos custom para criar, retorne customFields como array vazio []
- Responda APENAS com o JSON, sem ```json, sem explicação, sem texto antes ou depois

DOCUMENTO:
PROMPT;

    $payload = json_encode([
        'model'       => 'grok-4.3',
        'messages'    => [
            ['role' => 'system', 'content' => $prompt],
            ['role' => 'user', 'content' => $specContent],
        ],
        'max_tokens'  => 4096,
        'temperature' => 0.1,
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

    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) return null;

    $dados = json_decode($resp, true);
    $content = $dados['choices'][0]['message']['content'] ?? '';

    // Limpa possíveis wrappers markdown
    $content = trim($content);
    $content = preg_replace('/^```json\s*/i', '', $content);
    $content = preg_replace('/```\s*$/', '', $content);
    $content = trim($content);

    // Valida se é JSON válido com a estrutura esperada
    $manifest = json_decode($content, true);
    if (!$manifest || !isset($manifest['metadata'])) return null;

    // Garante specName
    if (empty($manifest['specName'])) {
        $manifest['specName'] = 'AutoDeploy_' . date('Y-m-d_His');
    }

    return json_encode($manifest);
}

/**
 * Busca informações da org conectada (com cache em sessão)
 */
function buscarInfoOrg(): ?array {
    // Cache na sessão para não chamar toda vez
    if (!empty($_SESSION['org_info']) && ($_SESSION['org_info_ts'] ?? 0) > time() - 300) {
        return $_SESSION['org_info'];
    }

    $url = MCP_SERVER . '/test-connection';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) return null;

    $dados = json_decode($resp, true);
    if (!$dados || $dados['status'] !== 'connected') return null;

    // Monta info com URL da org
    $orgInfo = [
        'orgId'       => $dados['orgId'] ?? '?',
        'username'    => $dados['username'] ?? '?',
        'displayName' => $dados['displayName'] ?? '?',
        'loginUrl'    => $dados['instanceUrl']
            ?? ('https://' . str_replace('.my.salesforce.com', '', parse_url(MCP_SERVER, PHP_URL_HOST)) . '.my.salesforce.com'),
    ];

    // Tenta pegar a URL real da org via instanceUrl
    if (empty($dados['instanceUrl'])) {
        // Fallback: constrói a partir do orgId
        $orgInfo['loginUrl'] = 'Acessar via DevHub ou Setup';
    }

    $_SESSION['org_info'] = $orgInfo;
    $_SESSION['org_info_ts'] = time();

    return $orgInfo;
}

/**
 * Base64 URL-safe encode
 */
function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
