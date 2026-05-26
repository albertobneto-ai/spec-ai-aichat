# AI Chat — Guia Completo de Instalação

## Visão Geral do Projeto

Um chat com IA hospedado no seu servidor PHP, com:
- Login por usuário e senha
- Fluxo de primeiro acesso via link (você cria o usuário, ele define a senha)
- Painel admin para gerenciar usuários e base de conhecimento
- Integração com OpenRouter (modelos gratuitos)
- Base de conhecimento: a IA consulta seus arquivos antes de responder

---

## Estrutura de Arquivos

```
/aichat/
├── index.php              → Tela de login
├── chat.php               → Interface do chat (requer login)
├── logout.php             → Encerra a sessão
├── primeiro-acesso.php    → Usuário define sua senha pelo link
│
├── proxy.php              → Intermediário com o OpenRouter + busca na KB
├── auth.php               → Funções de autenticação
├── db.php                 → Conexão com o banco MySQL
├── config.php             ★ EDITE ESTE ARQUIVO com suas credenciais
│
├── admin/
│   ├── index.php          → Lista de usuários
│   ├── criar.php          → Criar usuário / gerar link de acesso
│   └── kb.php             → Upload de arquivos da base de conhecimento
│
├── knowledge_base/
│   ├── .htaccess          → Bloqueia acesso direto aos arquivos
│   └── (seus arquivos .txt e .md ficam aqui)
│
└── banco.sql              → Execute no MySQL antes de começar
```

---

## Passo a Passo de Instalação

### 1. Criar o Banco de Dados (Hostinger)

1. Acesse o **hPanel** da Hostinger
2. Vá em **Banco de Dados → MySQL**
3. Clique em **Criar novo banco de dados**
4. Anote: nome do banco, usuário e senha
5. Clique em **phpMyAdmin** ao lado do banco criado
6. Clique na aba **SQL**
7. Cole o conteúdo do arquivo `banco.sql` e clique em **Executar**

---

### 2. Editar o config.php

Abra o arquivo `config.php` e preencha:

```php
// Sua chave do OpenRouter (https://openrouter.ai/keys)
define('OPENROUTER_KEY', 'sk-or-SUA_CHAVE_AQUI');

// Dados do banco MySQL criado no passo anterior
define('DB_HOST', 'localhost');
define('DB_NAME', 'nome_do_banco');
define('DB_USER', 'usuario_do_banco');
define('DB_PASS', 'senha_do_banco');

// Senha para acessar o painel /admin/  — TROQUE!
define('ADMIN_SENHA', 'SuaSenhaSegura123');
```

---

### 3. Fazer Upload dos Arquivos

1. Acesse o **Gerenciador de Arquivos** no hPanel
   *(ou use FTP: FileZilla, Cyberduck, etc.)*
2. Navegue até a pasta do seu domínio:
   - Se for o domínio principal: `public_html/`
   - Se for um subdomínio (ex: `chat.seusite.com`): pasta configurada para ele
3. Crie uma pasta chamada `aichat` (ou suba na raiz, como preferir)
4. Faça upload de **todos os arquivos** do projeto mantendo a estrutura de pastas

> ⚠️ **Importante:** a pasta `knowledge_base/` deve existir no servidor.
> Se o upload não criar ela automaticamente (por estar vazia), crie-a manualmente
> no Gerenciador de Arquivos.

---

### 4. Testar o Acesso

Abra no navegador:
```
https://seusite.com/aichat/admin/
```

Digite a senha definida em `config.php` > `ADMIN_SENHA`.

Se abrir o painel, a instalação está correta. ✓

---

### 5. Criar o Primeiro Usuário

1. No painel admin, clique em **+ Novo usuário**
2. Preencha nome e e-mail
3. Clique em **Criar usuário e gerar link**
4. Copie o link gerado e envie para o usuário

O usuário acessa o link, define a senha e já pode entrar em:
```
https://seusite.com/aichat/
```

---

## Base de Conhecimento

### Como funciona

Quando o usuário envia uma mensagem, o sistema:
1. Analisa as palavras-chave da pergunta
2. Busca trechos relevantes nos arquivos `.txt` e `.md` da pasta `knowledge_base/`
3. Injeta os trechos mais relevantes no início do prompt enviado à IA
4. A IA responde priorizando esse conteúdo

### Adicionar arquivos

1. Acesse `/admin/` e clique em **Base de Conhecimento**
2. Faça upload de arquivos `.txt` ou `.md`
3. Pronto — a IA já começa a usar nas próximas perguntas

### Dicas para bons resultados

- **Organize por tema:** um arquivo por assunto (ex: `agentforce.txt`, `service-cloud.txt`)
- **Use linguagem clara:** escreva como se fosse um manual ou FAQ
- **Títulos ajudam:** comece cada seção com um título descritivo
- **Para PDFs:** abra no Word ou Google Docs → Arquivo → Download como `.txt`
- **Tamanho recomendado:** até 2 MB por arquivo para melhor performance

### Exemplo de arquivo .txt bem estruturado

```
AGENTFORCE — VISÃO GERAL

O Agentforce é a plataforma de agentes de IA da Salesforce...

COMPONENTES PRINCIPAIS

Agent Builder: interface low-code para configurar agentes...
Atlas Reasoning Engine: motor de raciocínio que decide...

CASOS DE USO

- Atendimento ao cliente automatizado no Service Cloud
- Triagem de leads no Sales Cloud
...
```

---

## Painel Admin

| URL | Função |
|---|---|
| `/admin/` | Lista de usuários, ativar/desativar |
| `/admin/criar.php` | Criar usuário e gerar link de primeiro acesso |
| `/admin/kb.php` | Gerenciar arquivos da base de conhecimento |

---

## Perguntas Frequentes

**O usuário esqueceu a senha. O que faço?**
No painel admin, clique em **Reenviar link** ao lado do usuário. Isso gera um novo link de redefinição (o anterior deixa de funcionar).

**Como trocar os modelos disponíveis?**
Edite o array `MODELOS` no `config.php`. Consulte a lista de modelos gratuitos em [openrouter.ai/models](https://openrouter.ai/models) (filtre por "Free").

**Posso adicionar mais de um admin?**
No momento a senha do admin é única e definida em `config.php`. Para múltiplos admins, seria necessário adaptar o código (faça isso em uma próxima etapa).

**Posso usar arquivos PDF diretamente?**
Ainda não — o sistema aceita `.txt` e `.md`. Converta o PDF para `.txt` antes de fazer o upload.

**O histórico de conversa é salvo?**
Não. O histórico existe apenas durante a sessão do navegador. Ao fechar ou sair, é apagado. Isso pode ser adicionado futuramente com poucas alterações.

---

## Segurança

- A chave do OpenRouter fica **somente no servidor** (nunca exposta ao browser)
- A pasta `knowledge_base/` tem `.htaccess` bloqueando acesso direto
- As senhas são armazenadas com **bcrypt** (padrão seguro do PHP)
- A sessão expira após **8 horas** de inatividade (configurável em `config.php`)

---

## Próximas Evoluções (quando quiser)

- [ ] Histórico de conversas salvo por usuário no banco MySQL
- [ ] Suporte a PDF direto (conversão server-side)
- [ ] System prompt personalizado por usuário ou grupo
- [ ] Limite de mensagens por usuário/dia
- [ ] Múltiplos admins com login próprio
