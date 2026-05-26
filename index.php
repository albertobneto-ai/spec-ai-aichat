<?php
require_once __DIR__ . '/auth.php';
iniciarSessao();

if (!empty($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . '/chat.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';

    if (tentarLogin($email, $senha)) {
        header('Location: ' . BASE_URL . '/chat.php');
        exit;
    } else {
        $erro = 'E-mail ou senha incorretos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>AI Chat — Login</title>
<link rel="icon" type="image/png" href="<?= BASE_URL ?>/favicon.png"/>
<link rel="apple-touch-icon" href="<?= BASE_URL ?>/apple-touch-icon.png"/>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#0f0f0f;--surface:#1a1a1a;--border2:#333;--text:#e8e8e8;--text2:#888;--text3:#444;--accent:#ffffff;}
html,body{height:100%;background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;}
body{display:flex;flex-direction:column;align-items:center;justify-content:center;}
.box{width:100%;max-width:360px;padding:0 24px;display:flex;flex-direction:column;gap:28px;}
.logo{font-size:14px;font-weight:500;color:var(--text2);text-align:center;}
.form{display:flex;flex-direction:column;gap:12px;}
.field{display:flex;flex-direction:column;gap:6px;}
.field label{font-size:12px;color:var(--text2);}
.field input{background:var(--surface);border:1px solid var(--border2);border-radius:9px;padding:11px 14px;font-size:14px;font-family:'Inter',sans-serif;color:var(--text);outline:none;transition:border-color .2s;}
.field input:focus{border-color:#555;}
.field input::placeholder{color:var(--text3);}
.btn{margin-top:4px;padding:12px;border-radius:9px;background:var(--accent);border:none;cursor:pointer;font-size:14px;font-weight:500;font-family:'Inter',sans-serif;color:#0f0f0f;transition:opacity .15s;}
.btn:hover{opacity:.88;}
.erro{font-size:12.5px;color:#f87171;text-align:center;background:#f8717115;border:1px solid #f8717130;border-radius:8px;padding:9px 14px;}
.rodape{font-size:11.5px;color:var(--text3);text-align:center;}
</style>
</head>
<body>
<div class="box">
  <div class="logo">AI Chat</div>
  <div class="form">
    <?php if ($erro): ?>
      <div class="erro"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>
    <form method="POST">
      <div class="form" style="gap:12px">
        <div class="field">
          <label>E-mail</label>
          <input type="email" name="email" placeholder="seu@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus/>
        </div>
        <div class="field">
          <label>Senha</label>
          <input type="password" name="senha" placeholder="••••••••" required/>
        </div>
        <button type="submit" class="btn">Entrar</button>
      </div>
    </form>
  </div>
  <div class="rodape">Acesso restrito · Entre em contato com o administrador para obter acesso.</div>
</div>
</body>
</html>
