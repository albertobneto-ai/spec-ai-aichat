<?php
// ============================================================
//  primeiro-acesso.php  —  Usuário define sua senha pelo token
//  URL de acesso: /primeiro-acesso.php?token=XXXXX
// ============================================================
require_once __DIR__ . '/auth.php';

$token   = trim($_GET['token'] ?? '');
$usuario = $token ? validarToken($token) : null;
$erro    = '';
$ok      = false;

if (!$usuario) {
    $erro = 'Link inválido ou expirado. Solicite um novo link ao administrador.';
}

if ($usuario && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $senha1 = $_POST['senha']    ?? '';
    $senha2 = $_POST['confirma'] ?? '';

    if (strlen($senha1) < 8) {
        $erro = 'A senha deve ter no mínimo 8 caracteres.';
    } elseif ($senha1 !== $senha2) {
        $erro = 'As senhas não coincidem.';
    } else {
        definirSenha($usuario['id'], $senha1);
        $ok = true;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>AI Chat — Definir Senha</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0f0f0f;--surface:#1a1a1a;--border2:#333;
  --text:#e8e8e8;--text2:#888;--text3:#444;--accent:#ffffff;
}
html,body{height:100%;background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;}
body{display:flex;align-items:center;justify-content:center;}
.box{width:100%;max-width:360px;padding:0 24px;display:flex;flex-direction:column;gap:24px;}
.logo{font-size:14px;font-weight:500;color:var(--text2);text-align:center;}
.titulo{font-size:18px;font-weight:600;text-align:center;letter-spacing:-.2px;}
.sub{font-size:13px;color:var(--text2);text-align:center;line-height:1.6;}
.form{display:flex;flex-direction:column;gap:12px;}
.field{display:flex;flex-direction:column;gap:6px;}
.field label{font-size:12px;color:var(--text2);}
.field input{
  background:var(--surface);border:1px solid var(--border2);border-radius:9px;
  padding:11px 14px;font-size:14px;font-family:'Inter',sans-serif;
  color:var(--text);outline:none;transition:border-color .2s;
}
.field input:focus{border-color:#555;}
.btn{
  padding:12px;border-radius:9px;background:var(--accent);border:none;cursor:pointer;
  font-size:14px;font-weight:500;font-family:'Inter',sans-serif;color:#0f0f0f;transition:opacity .15s;
}
.btn:hover{opacity:.88;}
.btn-link{
  display:block;text-align:center;padding:12px;border-radius:9px;
  background:var(--accent);font-size:14px;font-weight:500;font-family:'Inter',sans-serif;
  color:#0f0f0f;text-decoration:none;transition:opacity .15s;
}
.btn-link:hover{opacity:.88;}
.erro{font-size:12.5px;color:#f87171;background:#f8717115;border:1px solid #f8717130;border-radius:8px;padding:9px 14px;text-align:center;}
.sucesso{font-size:13px;color:#4ade80;background:#4ade8015;border:1px solid #4ade8030;border-radius:8px;padding:9px 14px;text-align:center;}
</style>
</head>
<body>
<div class="box">
  <div class="logo">AI Chat</div>

  <?php if ($ok): ?>
    <div class="titulo">Senha definida!</div>
    <div class="sucesso">Sua senha foi criada com sucesso.</div>
    <a class="btn-link" href="<?= BASE_URL ?>/index.php">Ir para o login</a>

  <?php elseif (!$usuario): ?>
    <div class="titulo">Link inválido</div>
    <div class="erro"><?= htmlspecialchars($erro) ?></div>

  <?php else: ?>
    <div class="titulo">Olá, <?= htmlspecialchars(explode(' ', $usuario['nome'])[0]) ?>.</div>
    <div class="sub">Defina sua senha para acessar o AI Chat.</div>

    <form method="POST" class="form">
      <?php if ($erro): ?>
        <div class="erro"><?= htmlspecialchars($erro) ?></div>
      <?php endif; ?>
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>"/>
      <div class="field">
        <label>Nova senha <span style="color:#555">(mín. 8 caracteres)</span></label>
        <input type="password" name="senha" placeholder="••••••••" required autofocus/>
      </div>
      <div class="field">
        <label>Confirme a senha</label>
        <input type="password" name="confirma" placeholder="••••••••" required/>
      </div>
      <button type="submit" class="btn">Definir senha e entrar</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
