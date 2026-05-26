<?php
// ============================================================
//  alias-map.php  —  Mapeamento PT-BR → Salesforce API Names
//  Permite /describe conta, /describe oportunidades, etc.
// ============================================================

define('SF_ALIASES', [

    // ── Account ──
    'conta'         => 'Account',
    'contas'        => 'Account',
    'account'       => 'Account',
    'accounts'      => 'Account',
    'empresa'       => 'Account',
    'empresas'      => 'Account',
    'cliente'       => 'Account',
    'clientes'      => 'Account',

    // ── Contact ──
    'contato'       => 'Contact',
    'contatos'      => 'Contact',
    'contact'       => 'Contact',
    'contacts'      => 'Contact',

    // ── Lead ──
    'lead'          => 'Lead',
    'leads'         => 'Lead',
    'prospecto'     => 'Lead',
    'prospectos'    => 'Lead',
    'prospect'      => 'Lead',

    // ── Opportunity ──
    'oportunidade'  => 'Opportunity',
    'oportunidades' => 'Opportunity',
    'opportunity'   => 'Opportunity',
    'opportunities' => 'Opportunity',
    'opp'           => 'Opportunity',
    'opps'          => 'Opportunity',
    'deal'          => 'Opportunity',
    'deals'         => 'Opportunity',
    'negócio'       => 'Opportunity',
    'negocios'      => 'Opportunity',
    'negócios'      => 'Opportunity',

    // ── Case ──
    'caso'          => 'Case',
    'casos'         => 'Case',
    'case'          => 'Case',
    'cases'         => 'Case',
    'chamado'       => 'Case',
    'chamados'      => 'Case',
    'ticket'        => 'Case',
    'tickets'       => 'Case',
    'ocorrência'    => 'Case',
    'ocorrencia'    => 'Case',
    'ocorrências'   => 'Case',
    'ocorrencias'   => 'Case',

    // ── Campaign ──
    'campanha'      => 'Campaign',
    'campanhas'     => 'Campaign',
    'campaign'      => 'Campaign',
    'campaigns'     => 'Campaign',

    // ── Quote ──
    'cotação'       => 'Quote',
    'cotacao'       => 'Quote',
    'cotações'      => 'Quote',
    'cotacoes'      => 'Quote',
    'quote'         => 'Quote',
    'quotes'        => 'Quote',
    'proposta'      => 'Quote',
    'propostas'     => 'Quote',

    // ── Order ──
    'pedido'        => 'Order',
    'pedidos'       => 'Order',
    'order'         => 'Order',
    'orders'        => 'Order',
    'ordem'         => 'Order',
    'ordens'        => 'Order',

    // ── Contract ──
    'contrato'      => 'Contract',
    'contratos'     => 'Contract',
    'contract'      => 'Contract',
    'contracts'     => 'Contract',

    // ── Product ──
    'produto'       => 'Product2',
    'produtos'      => 'Product2',
    'product'       => 'Product2',
    'products'      => 'Product2',

    // ── Pricebook ──
    'tabela de preço'   => 'Pricebook2',
    'tabela de precos'  => 'Pricebook2',
    'catálogo'          => 'Pricebook2',
    'catalogo'          => 'Pricebook2',
    'pricebook'         => 'Pricebook2',
    'price book'        => 'Pricebook2',

    // ── PricebookEntry ──
    'entrada de preço'  => 'PricebookEntry',
    'item do catálogo'  => 'PricebookEntry',
    'pricebookentry'    => 'PricebookEntry',

    // ── OpportunityLineItem ──
    'produto da oportunidade' => 'OpportunityLineItem',
    'item da oportunidade'    => 'OpportunityLineItem',
    'opportunitylineitem'     => 'OpportunityLineItem',
    'opp product'             => 'OpportunityLineItem',
    'linha da oportunidade'   => 'OpportunityLineItem',

    // ── Task ──
    'tarefa'        => 'Task',
    'tarefas'       => 'Task',
    'task'          => 'Task',
    'tasks'         => 'Task',
    'atividade'     => 'Task',
    'atividades'    => 'Task',

    // ── Event ──
    'evento'        => 'Event',
    'eventos'       => 'Event',
    'event'         => 'Event',
    'events'        => 'Event',
    'reunião'       => 'Event',
    'reuniao'       => 'Event',
    'reuniões'      => 'Event',
    'reunioes'      => 'Event',

    // ── User ──
    'usuário'       => 'User',
    'usuario'       => 'User',
    'usuários'      => 'User',
    'usuarios'      => 'User',
    'user'          => 'User',
    'users'         => 'User',

    // ── Asset ──
    'ativo'         => 'Asset',
    'ativos'        => 'Asset',
    'asset'         => 'Asset',
    'assets'        => 'Asset',

    // ── CampaignMember ──
    'membro da campanha'    => 'CampaignMember',
    'membros da campanha'   => 'CampaignMember',
    'campaignmember'        => 'CampaignMember',

    // ── OpportunityContactRole ──
    'papel do contato'      => 'OpportunityContactRole',
    'contact role'          => 'OpportunityContactRole',

    // ── Territory ──
    'território'    => 'Territory2',
    'territorio'    => 'Territory2',
    'territórios'   => 'Territory2',
    'territorios'   => 'Territory2',
    'territory'     => 'Territory2',

    // ── Dashboard / Report ──
    'relatório'     => 'Report',
    'relatorio'     => 'Report',
    'relatórios'    => 'Report',
    'relatorios'    => 'Report',
    'report'        => 'Report',
    'dashboard'     => 'Dashboard',
    'painel'        => 'Dashboard',
    'painéis'       => 'Dashboard',
    'paineis'       => 'Dashboard',

    // ── ContentDocument ──
    'documento'     => 'ContentDocument',
    'documentos'    => 'ContentDocument',
    'arquivo'       => 'ContentDocument',
    'arquivos'      => 'ContentDocument',

    // ── EmailMessage ──
    'email'         => 'EmailMessage',
    'emails'        => 'EmailMessage',
    'e-mail'        => 'EmailMessage',

    // ── QuoteLineItem ──
    'item da cotação'      => 'QuoteLineItem',
    'item da cotacao'      => 'QuoteLineItem',
    'linha da cotação'     => 'QuoteLineItem',
    'quotelineitem'        => 'QuoteLineItem',

    // ── OrderItem ──
    'item do pedido'       => 'OrderItem',
    'linha do pedido'      => 'OrderItem',
    'orderitem'            => 'OrderItem',
]);

/**
 * Resolve um input do usuário para o API Name correto.
 * Aceita: nome em PT-BR, EN, singular/plural, com/sem acentos, __c custom.
 */
function resolverObjeto(string $input): array {
    $input = trim($input);
    $inputLower = mb_strtolower($input);

    // Se já termina com __c ou __mdt ou __e, é custom — usar direto
    if (preg_match('/__[cCeE]$|__mdt$/i', $input)) {
        return ['apiName' => $input, 'resolved' => false, 'original' => $input];
    }

    // Busca exata no mapa (case-insensitive)
    if (isset(SF_ALIASES[$inputLower])) {
        $apiName = SF_ALIASES[$inputLower];
        $wasTranslated = (mb_strtolower($apiName) !== $inputLower);
        return ['apiName' => $apiName, 'resolved' => $wasTranslated, 'original' => $input];
    }

    // Tenta sem acentos
    $semAcento = removeAcentos($inputLower);
    if (isset(SF_ALIASES[$semAcento])) {
        return ['apiName' => SF_ALIASES[$semAcento], 'resolved' => true, 'original' => $input];
    }

    // Busca parcial (contém)
    foreach (SF_ALIASES as $alias => $apiName) {
        if (str_contains($alias, $inputLower) || str_contains($inputLower, $alias)) {
            return ['apiName' => $apiName, 'resolved' => true, 'original' => $input];
        }
    }

    // Não encontrou — assume que é o API Name direto (PascalCase)
    // Tenta capitalizar: "account" → "Account"
    $capitalized = ucfirst($inputLower);
    return ['apiName' => $capitalized, 'resolved' => false, 'original' => $input];
}

function removeAcentos(string $str): string {
    $map = [
        'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'ç'=>'c','ñ'=>'n',
    ];
    return strtr($str, $map);
}
