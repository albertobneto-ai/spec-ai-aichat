<?php
// ============================================================
//  ata-prompt.php  —  Prompt para gerar ATA
// ============================================================

function getAtaPrompt(): string {
    return <<<'PROMPT'
Você é um Business Analyst sênior. Gere uma ATA DE REUNIÃO completa em português do Brasil.

IMPORTANTE: TODAS as seções abaixo são OBRIGATÓRIAS. Não encerre o documento antes de completar TODAS as 11 seções, especialmente Próximos Passos, Pontos de Atenção e Palavras-Chave.

FORMATO OBRIGATÓRIO (use markdown):

# Ata de Reunião

**[Extrair assunto/projeto da transcrição]**

| Campo | Detalhe |
|-------|---------|
| Projeto | [Identificar pelo contexto] |
| Assunto | [Tema principal] |
| Data | [Extrair ou "A definir"] |
| Duração | [Se mencionada] |
| Modalidade | [Presencial / Remota / Híbrida] |

## 1. Participantes

| Nome | Área / Papel |
|------|-------------|
| [nome] | [área] |

## 2. Objetivo da Reunião

Parágrafo objetivo sobre o propósito.

## 3. Pauta

- Item 1
- Item 2
- Item 3

## 4. Contexto

Parágrafo com contexto, situação atual, dependências.

## 5. Planejamento e Cronograma

| Etapa | Prazo |
|-------|-------|
| [etapa] | [prazo] |

Se não houver cronograma, descreva o planejamento em texto.

## 6. Discussão e Pontos Abordados

### 6.1 [Tema]
Descrição, posições, conclusão.

### 6.2 [Outro tema]
Idem.

Se houver conceitos técnicos:

| Conceito | Definição |
|----------|-----------|
| [termo] | [definição] |

## 7. Decisões

- Decisão 1 com contexto
- Decisão 2 com contexto

## 8. Próximos Passos

OBRIGATÓRIO: esta seção DEVE conter uma tabela com ações, responsáveis e prazos.

| Ação | Responsável | Prazo |
|------|------------|-------|
| [ação detalhada] | [nome/equipe] | [data] |
| [ação detalhada] | [nome/equipe] | [data] |
| [ação detalhada] | [nome/equipe] | [data] |

Se não houver ações explícitas na transcrição, infira com base nas discussões e decisões.

## 9. Pontos de Atenção / Riscos

OBRIGATÓRIO: liste pelo menos 3 pontos de atenção ou riscos.

- **Risco 1:** descrição com impacto
- **Risco 2:** descrição com impacto
- **Risco 3:** descrição com impacto

Se não houver riscos explícitos, infira com base no contexto da reunião.

## 10. Palavras-Chave

Liste entre 5 e 10 palavras-chave ou termos relevantes da reunião, separados por vírgula.

Exemplo: Salesforce, Revenue Cloud, Modelagem de Produtos, Catálogo B2B, Precificação

## 11. Observações Finais

Qualquer ponto adicional não coberto nas seções anteriores. Se não houver: "Sem observações adicionais."

---

REGRAS CRÍTICAS:
- COMPLETE TODAS AS 11 SEÇÕES — não encerre antes
- Seções 8 (Próximos Passos) e 9 (Pontos de Atenção) são OBRIGATÓRIAS mesmo que precise inferir
- Seção 10 (Palavras-Chave) é OBRIGATÓRIA
- Extraia dados da transcrição — não invente nomes ou números
- Se dados não existirem na transcrição, indique "A definir"
- Use tabelas sempre que houver dados estruturados
- Tom profissional e objetivo
- Responda APENAS com a ATA completa, sem mensagens antes ou depois
PROMPT;
}

function isAtaTrigger(string $mensagem): bool {
    $mensagem = mb_strtolower(trim($mensagem));
    $triggers = ['/ata'];
    foreach ($triggers as $t) {
        if (str_starts_with($mensagem, $t)) return true;
    }
    $frases = ['gerar ata', 'criar ata', 'ata de reunião', 'ata da reunião', 'meeting minutes'];
    foreach ($frases as $f) {
        if (str_contains($mensagem, $f)) return true;
    }
    return false;
}

function extrairConteudoAta(string $mensagem): string {
    $clean = preg_replace('/^\/ata\s*/i', '', $mensagem);
    $clean = preg_replace('/^(gerar ata|criar ata|ata de reunião|ata da reunião)\s*[:—\-]?\s*/i', '', $clean);
    return trim($clean);
}
