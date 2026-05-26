<?php
// ============================================================
//  chat.php  —  Interface principal do chat
//  Requer sessão ativa; redireciona para login se não autenticado
// ============================================================
require_once __DIR__ . '/auth.php';
exigirLogin();

$user      = usuarioLogado();
$primeiroNome = explode(' ', $user['nome'])[0];
$modelos   = MODELOS; // array definido em config.php
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover"/>
<title>Spec AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0f0f0f;--surface:#1a1a1a;--surface2:#242424;
  --border:#2a2a2a;--border2:#333;
  --text:#e8e8e8;--text2:#888;--text3:#444;--accent:#ffffff;--r:10px;
}
html,body{height:100vh;height:100dvh;background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;overflow:hidden}
header{
  height:48px;display:flex;align-items:center;justify-content:space-between;
  padding:0 28px;border-bottom:1px solid var(--border);background:var(--bg);flex-shrink:0;
}
.logo{display:flex;align-items:center;gap:8px;white-space:nowrap;}
.logo-img{height:28px;width:auto;border-radius:5px;}
.logo-text{font-size:13px;font-weight:500;color:var(--text2);}
.model-select{display:flex;align-items:center;gap:5px;min-width:0;}
.model-select select{
  background:none;border:none;outline:none;color:var(--text3);
  font-family:'Inter',sans-serif;font-size:13px;cursor:pointer;appearance:none;
}
.model-select select option{background:#1a1a1a;}
.sel-arrow{color:var(--text3);font-size:9px;pointer-events:none;}
.header-right{display:flex;align-items:center;gap:16px;white-space:nowrap;}
.uname{font-size:13px;color:var(--text3);}
.logout-btn{
  font-size:12px;color:var(--text3);background:none;border:none;
  cursor:pointer;font-family:'Inter',sans-serif;transition:color .15s;padding:0;
}
.logout-btn:hover{color:var(--text2);}
.body{display:flex;flex-direction:column;height:calc(100vh - 48px);height:calc(100dvh - 48px);}
.messages{
  flex:1;overflow-y:auto;display:flex;flex-direction:column;padding:40px 0 16px;
}
.messages::-webkit-scrollbar{width:3px;}
.messages::-webkit-scrollbar-thumb{background:var(--surface2);border-radius:3px;}
.msg-row{width:100%;max-width:720px;margin:0 auto;padding:4px 24px 14px;animation:up .2s ease both;}
@keyframes up{from{opacity:0;transform:translateY(5px)}to{opacity:1;transform:none}}
.msg-inner{display:flex;gap:13px;}
.msg-icon{
  width:27px;height:27px;border-radius:7px;flex-shrink:0;margin-top:2px;
  display:flex;align-items:center;justify-content:center;font-size:12px;
}
.msg-icon.ai{background:var(--accent);color:#0f0f0f;font-weight:700;}
.msg-icon.user{background:var(--surface2);border:1px solid var(--border2);color:var(--text2);}
.msg-content{flex:1;min-width:0;}
.msg-role{font-size:12px;font-weight:500;margin-bottom:5px;color:var(--text2);letter-spacing:.1px;}
.msg-role.ai-role{color:var(--accent);}
.msg-text{font-size:14.5px;line-height:1.8;color:var(--text);font-weight:300;}
.msg-text p{margin-bottom:8px;}
.msg-text p:last-child{margin:0;}
.msg-text strong{font-weight:500;}
.msg-text code{
  font-family:'JetBrains Mono',monospace;font-size:12.5px;
  background:var(--surface2);border:1px solid var(--border);
  padding:1px 6px;border-radius:4px;color:#9a9;
}
.msg-text pre{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--r);padding:14px 16px;margin:10px 0;overflow-x:auto;
}
.msg-text pre code{background:none;border:none;padding:0;color:#9c9;}
.msg-actions{display:flex;gap:4px;margin-top:8px;opacity:0;transition:opacity .15s;}
.msg-row:hover .msg-actions{opacity:1;}
.act{
  background:none;border:1px solid var(--border);border-radius:6px;
  padding:3px 9px;font-size:11px;color:var(--text3);
  cursor:pointer;font-family:'Inter',sans-serif;transition:all .15s;
}
.act:hover{border-color:var(--border2);color:var(--text2);}
.sep{width:100%;max-width:720px;margin:4px auto 14px;padding:0 24px;}
.sep hr{border:none;border-top:1px solid var(--border);}
.typing-row{width:100%;max-width:720px;margin:0 auto;padding:6px 24px 14px;}
.typing-inner{display:flex;gap:13px;align-items:center;}
.dots{display:flex;gap:4px;padding-top:2px;}
.dots span{width:5px;height:5px;border-radius:50%;background:var(--text3);animation:bl 1.2s infinite;}
.dots span:nth-child(2){animation-delay:.2s;}
.dots span:nth-child(3){animation-delay:.4s;}
@keyframes bl{0%,100%{opacity:.2}50%{opacity:1}}
.input-area{
  padding:12px 24px 22px;flex-shrink:0;
  padding-bottom:calc(22px + env(safe-area-inset-bottom, 0px));
  display:flex;flex-direction:column;align-items:center;
}
.input-shell{
  width:100%;max-width:720px;background:var(--surface);
  border:1px solid var(--border2);border-radius:12px;transition:border-color .2s;
}
.input-shell:focus-within{border-color:#3a3a3a;}
.input-top{display:flex;align-items:flex-end;padding:12px 10px 8px 16px;gap:10px;}
textarea{
  flex:1;background:none;border:none;outline:none;
  color:var(--text);font-family:'Inter',sans-serif;
  font-size:14px;font-weight:300;line-height:1.6;resize:none;min-height:22px;max-height:150px;
}
textarea::placeholder{color:var(--text3);}
.send-btn{
  width:33px;height:33px;border-radius:8px;flex-shrink:0;
  background:var(--accent);border:none;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  font-size:13px;color:#0f0f0f;transition:opacity .15s,transform .1s;
}
.send-btn:hover{opacity:.85;}
.send-btn:active{transform:scale(.94);}
.send-btn:disabled{background:var(--surface2);color:var(--text3);cursor:not-allowed;opacity:1;}
.input-footer{
  display:flex;align-items:center;justify-content:space-between;
  padding:5px 14px 10px;border-top:1px solid var(--border);
}
.hint{font-size:11px;color:var(--text3);}
.hint kbd{
  font-family:'JetBrains Mono',monospace;font-size:10px;
  background:var(--surface2);border:1px solid var(--border);padding:1px 5px;border-radius:4px;
}
.cc{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--text3);}
.empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;padding:40px 24px;}
.welcome-logo{width:120px;height:auto;border-radius:16px;opacity:0.9;}
.empty-title{font-size:26px;font-weight:600;letter-spacing:-.5px;}
.empty-sub{font-size:13.5px;color:var(--text2);text-align:center;line-height:1.7;max-width:340px;}
.erro-toast{
  position:fixed;bottom:90px;left:50%;transform:translateX(-50%);
  background:#1a1a1a;border:1px solid #f87171;border-radius:9px;
  padding:10px 18px;font-size:13px;color:#f87171;z-index:99;
  animation:up .2s ease both;display:none;
}
/* Banner HF — azul */
.hf-banner{
  display:flex;align-items:center;gap:10px;
  background:#1a2332;border:1px solid #2d4a6f;border-radius:9px;
  padding:10px 14px;margin-bottom:12px;animation:up .3s ease both;
}
.hf-badge{
  background:#3b82f6;color:#fff;font-size:10px;font-weight:700;
  padding:2px 8px;border-radius:4px;letter-spacing:1px;
}
.hf-banner span{font-size:13px;color:#93c5fd;flex:1;}
.hf-download-btn{
  background:#3b82f6;color:#fff;border:none;border-radius:7px;
  padding:6px 14px;font-size:12px;font-weight:500;cursor:pointer;
  font-family:'Inter',sans-serif;transition:background .15s;white-space:nowrap;
}
.hf-download-btn:hover{background:#2563eb;}
/* Banner SPEC — verde */
.spec-banner{
  display:flex;align-items:center;gap:10px;
  background:#0d2818;border:1px solid #166534;border-radius:9px;
  padding:10px 14px;margin-bottom:12px;animation:up .3s ease both;
}
.spec-badge{
  background:#16a34a;color:#fff;font-size:10px;font-weight:700;
  padding:2px 8px;border-radius:4px;letter-spacing:1px;
}
.spec-banner span{font-size:13px;color:#86efac;flex:1;}
.spec-download-btn{
  background:#16a34a;color:#fff;border:none;border-radius:7px;
  padding:6px 14px;font-size:12px;font-weight:500;cursor:pointer;
  font-family:'Inter',sans-serif;transition:background .15s;white-space:nowrap;
}
.spec-download-btn:hover{background:#15803d;}

/* ═══ MOBILE ═══ */
@media (max-width: 600px) {
  header {
    padding: 0 12px;
    height: 44px;
    gap: 6px;
  }
  .logo { font-size: 12px; }
  .logo-img { height: 24px; }
  .logo-text { display: none; }
  .model-select { font-size: 11px; }
  #model-label { font-size: 10px !important; max-width: 120px; overflow: hidden; text-overflow: ellipsis; }
  .header-right { gap: 10px; }
  .uname { display: none; }
  .logout-btn { font-size: 11px; }

  .body { height: calc(100vh - 44px); }

  .body { height: calc(100dvh - 44px); }

  .messages { padding: 20px 0 10px; }
  .msg-row { padding: 4px 12px 10px; }
  .msg-inner { gap: 8px; }
  .msg-icon { width: 24px; height: 24px; font-size: 10px; border-radius: 6px; }
  .msg-role { font-size: 11px; }
  .msg-text { font-size: 13.5px; line-height: 1.7; }
  .msg-text code { font-size: 11.5px; }
  .msg-text pre { padding: 10px 12px; margin: 8px 0; }
  .msg-actions { opacity: 1; }

  .sep { padding: 0 12px; margin: 2px auto 8px; }
  .typing-row { padding: 4px 12px 10px; }

  .input-area {
    padding: 8px 10px 18px;
    padding-bottom: calc(18px + env(safe-area-inset-bottom, 0px));
  }
  .input-shell { border-radius: 10px; }
  .input-top { padding: 10px 8px 6px 12px; gap: 8px; }
  textarea { font-size: 16px; }
  .send-btn { width: 36px; height: 36px; }
  .input-footer { padding: 4px 10px 8px; }
  .hint { font-size: 10px; }
  .hint kbd { font-size: 9px; padding: 1px 4px; }
  .cc { font-size: 10px; }

  .empty { padding: 30px 16px; gap: 12px; }
  .welcome-logo { width: 90px; border-radius: 12px; }
  .empty-title { font-size: 22px; }
  .empty-sub { font-size: 12.5px; max-width: 280px; }

  .hf-banner, .spec-banner { padding: 8px 10px; gap: 8px; flex-wrap: wrap; }
  .hf-banner span, .spec-banner span { font-size: 12px; }
  .hf-download-btn, .spec-download-btn { padding: 5px 10px; font-size: 11px; }

  .erro-toast { bottom: 70px; font-size: 12px; padding: 8px 14px; }
}
</style>
</head>
<body>

<header>
  <div class="logo">
    <img src="<?= BASE_URL ?>/logo.jpg" alt="Spec AI" class="logo-img"/>
    <span class="logo-text">Spec AI</span>
  </div>

  <div class="model-select">
    <span id="model-label" style="font-size:12px;color:var(--text3);">Multi-modelo ativo</span>
  </div>

  <div class="header-right">
    <span class="uname"><?= htmlspecialchars($user['nome']) ?></span>
    <button class="logout-btn" onclick="location.href='<?= BASE_URL ?>/logout.php'">Sair</button>
  </div>
</header>

<div class="erro-toast" id="erroToast"></div>

<div class="body">
  <div class="messages" id="msgs">
    <div class="empty" id="empty">
      <img src="<?= BASE_URL ?>/logo.jpg" alt="Spec AI" class="welcome-logo"/>
      <div class="empty-title">Olá, <?= htmlspecialchars($primeiroNome) ?>.</div>
      <div class="empty-sub">Como posso ajudar você hoje?</div>
    </div>
  </div>

  <div class="input-area">
    <div class="input-shell">
      <div class="input-top">
        <textarea id="inp" placeholder="Mensagem..." rows="1"
          oninput="resize(this);cc()"
          onkeydown="hk(event)"></textarea>
        <button class="send-btn" id="sbtn" onclick="doSend()" disabled>➤</button>
      </div>
      <div class="input-footer">
        <span class="hint"><kbd>Enter</kbd> envia · <kbd>Shift+Enter</kbd> nova linha</span>
        <span class="cc" id="cc">0</span>
      </div>
    </div>
  </div>
</div>

<script>
const inp  = document.getElementById('inp');
const sbtn = document.getElementById('sbtn');
const msgs = document.getElementById('msgs');

// Histórico completo da conversa na sessão (somente memória do browser)
let historico = [];
let busy = false;

inp.addEventListener('input', () => { sbtn.disabled = !inp.value.trim() || busy; });

function resize(el){ el.style.height='auto'; el.style.height=Math.min(el.scrollHeight,150)+'px'; }
function cc(){ document.getElementById('cc').textContent = inp.value.length; }
function hk(e){ if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();doSend();} }

function agora(){
  return new Date().toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'});
}

function mostrarErro(msg){
  const t = document.getElementById('erroToast');
  t.textContent = msg;
  t.style.display = 'block';
  setTimeout(()=>{ t.style.display='none'; }, 4000);
}

// Converte markdown básico para HTML
function mdParaHtml(texto){
  return texto
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    // blocos de código
    .replace(/```[\w]*\n?([\s\S]*?)```/g,'<pre><code>$1</code></pre>')
    // negrito
    .replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>')
    // itálico
    .replace(/\*(.*?)\*/g,'<em>$1</em>')
    // código inline
    .replace(/`([^`]+)`/g,'<code>$1</code>')
    // quebras de linha
    .replace(/\n/g,'<br>');
}

function appendMsg(role, html, modelLabel, tipo){
  const empty = document.getElementById('empty');
  if(empty) empty.remove();

  const isAI = role === 'assistant';
  const isHF   = tipo === 'hf';
  const isAta  = tipo === 'ata';
  const isSpec = tipo === 'spec';
  const modelTag = (isAI && modelLabel) ? `<span style="font-size:10px;color:var(--text3);margin-left:6px;font-weight:300;">via ${modelLabel}</span>` : '';

  let docBanner = '';
  if (isHF) {
    docBanner = `<div class="hf-banner">
      <span class="hf-badge">HF</span>
      <span>História Funcional gerada</span>
      <button class="hf-download-btn" onclick="doWord(this)">⬇ Download .docx</button>
    </div>`;
  } else if (isAta) {
    docBanner = `<div class="hf-banner" style="border-color:#1D4ED844;background:#0F1D2E;">
      <span class="hf-badge" style="background:#1D4ED8;">ATA</span>
      <span style="color:#93C5FD;">Ata de Reunião gerada</span>
      <button class="hf-download-btn" style="background:#1D4ED8;" onmouseover="this.style.background='#1E40AF'" onmouseout="this.style.background='#1D4ED8'" onclick="doWord(this)">⬇ Download .docx</button>
    </div>`;
  } else if (isSpec) {
    docBanner = `<div class="spec-banner">
      <span class="spec-badge">SPEC</span>
      <span>Especificação Técnica gerada</span>
      <button class="spec-download-btn" onclick="doWordTipo(this,'spec')">⬇ Download .docx</button>
    </div>`;
  }

  const row  = document.createElement('div');
  row.className = 'msg-row';
  row.innerHTML = `
    <div class="msg-inner">
      <div class="msg-icon ${isAI?'ai':'user'}">${isAI?'◈':'↑'}</div>
      <div class="msg-content">
        <div class="msg-role ${isAI?'ai-role':''}">${isAI?'Assistente':'Você'} · ${agora()}${modelTag}</div>
        ${docBanner}
        <div class="msg-text">${html}</div>
        ${isAI?`<div class="msg-actions">
          <button class="act" onclick="doCopy(this)">Copiar</button>
          <button class="act" onclick="doWord(this)">Salvar Word</button>
        </div>`:''}
      </div>
    </div>`;
  msgs.appendChild(row);

  const sep = document.createElement('div');
  sep.className='sep'; sep.innerHTML='<hr/>';
  msgs.appendChild(sep);
  msgs.scrollTop = msgs.scrollHeight;
}

function showTyping(){
  const d = document.createElement('div');
  d.className='typing-row'; d.id='typing';
  d.innerHTML=`<div class="typing-inner">
    <div class="msg-icon ai">◈</div>
    <div class="dots"><span></span><span></span><span></span></div>
  </div>`;
  msgs.appendChild(d);
  msgs.scrollTop = msgs.scrollHeight;
}
function hideTyping(){ const d=document.getElementById('typing'); if(d) d.remove(); }

function doCopy(btn){
  const txt = btn.closest('.msg-content').querySelector('.msg-text').innerText;
  navigator.clipboard.writeText(txt);
  btn.textContent = 'Copiado ✓';
  setTimeout(()=>btn.textContent='Copiar', 2000);
}

async function doWord(btn){
  const txt = btn.closest('.msg-content').querySelector('.msg-text').innerText;
  btn.textContent = 'Gerando...';
  btn.disabled = true;

  try {
    const resp = await fetch('<?= BASE_URL ?>/download.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        conteudo: txt,
        titulo: txt.substring(0, 60).split('\n')[0],
        tipo: 'normal'
      })
    });

    if (!resp.ok) throw new Error('Erro');

    const blob = await resp.blob();
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = 'resposta-aichat.docx';
    a.click();
    URL.revokeObjectURL(url);

    btn.textContent = 'Salvo ✓';
    setTimeout(()=>{ btn.textContent='Salvar Word'; btn.disabled=false; }, 2000);
  } catch(e) {
    btn.textContent = 'Erro';
    setTimeout(()=>{ btn.textContent='Salvar Word'; btn.disabled=false; }, 2000);
  }
}

async function doWordTipo(btn, tipo){
  const txt = btn.closest('.msg-content').querySelector('.msg-text').innerText;
  btn.textContent = 'Gerando...';
  btn.disabled = true;

  const nomes = { hf: 'Historia_Funcional', ata: 'Ata_de_Reuniao', spec: 'Spec_Tecnica' };
  const nome = (nomes[tipo] || 'documento') + '_' + new Date().toISOString().slice(0,10);

  try {
    const resp = await fetch('<?= BASE_URL ?>/download.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        conteudo: txt,
        titulo: nome,
        tipo: tipo
      })
    });

    if (!resp.ok) throw new Error('Erro');

    const blob = await resp.blob();
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = nome + '.docx';
    a.click();
    URL.revokeObjectURL(url);

    btn.textContent = 'Salvo ✓';
    setTimeout(()=>{ btn.textContent='⬇ Download .docx'; btn.disabled=false; }, 2000);
  } catch(e) {
    btn.textContent = 'Erro';
    setTimeout(()=>{ btn.textContent='⬇ Download .docx'; btn.disabled=false; }, 2000);
  }
}

async function downloadDoc(conteudo, titulo, tipo){
  try {
    const resp = await fetch('<?= BASE_URL ?>/download.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        conteudo: conteudo,
        titulo: titulo,
        tipo: tipo
      })
    });

    if (!resp.ok) throw new Error('Erro');

    const blob = await resp.blob();
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = titulo + '.docx';
    a.click();
    URL.revokeObjectURL(url);
  } catch(e) {
    mostrarErro('Erro ao gerar o Word.');
  }
}

async function doSend(){
  const text = inp.value.trim();
  if(!text || busy) return;

  busy = true;
  sbtn.disabled = true;
  inp.value = '';
  inp.style.height = 'auto';
  document.getElementById('cc').textContent = '0';

  // Exibe mensagem do usuário
  appendMsg('user', text.replace(/\n/g,'<br>'));

  // Adiciona ao histórico
  historico.push({ role:'user', content: text });

  showTyping();

  try {
    const resp = await fetch('<?= BASE_URL ?>/proxy.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        messages: historico,
      })
    });

    const data = await resp.json();

    hideTyping();

    if (!resp.ok || data.erro) {
      mostrarErro(data.erro || 'Erro ao comunicar com a IA.');
    } else {
      const resposta    = data.choices[0].message.content;
      const modelLabel  = data.modelo_label || '';
      const tipo        = data.tipo || 'normal';
      historico.push({ role:'assistant', content: resposta });
      appendMsg('assistant', mdParaHtml(resposta), modelLabel, tipo);

      // Atualiza o header com o último modelo usado
      document.getElementById('model-label').textContent = modelLabel;

      // Se for HF, ATA ou SPEC, faz download automático do Word
      if (tipo === 'hf') {
        setTimeout(() => downloadDoc(resposta, 'Historia_Funcional', 'hf'), 800);
      } else if (tipo === 'ata') {
        setTimeout(() => downloadDoc(resposta, 'Ata_de_Reuniao', 'ata'), 800);
      } else if (tipo === 'spec') {
        setTimeout(() => downloadDoc(resposta, 'Spec_Tecnica_' + new Date().toISOString().slice(0,10), 'spec'), 800);
      }
    }
  } catch(err) {
    hideTyping();
    mostrarErro('Falha de conexão. Tente novamente.');
  }

  busy = false;
  sbtn.disabled = !inp.value.trim();
}
</script>
</body>
</html>
