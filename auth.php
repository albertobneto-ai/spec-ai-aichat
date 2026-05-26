<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function iniciarSessao(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_path', '/');
        ini_set('session.cookie_lifetime', SESSION_LIFETIME);
        session_name(SESSION_NAME);
        session_start();
    }
}

function exigirLogin(): void {
    iniciarSessao();
    if (empty($_SESSION['usuario_id'])) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function usuarioLogado(): array {
    return [
        'id'    => $_SESSION['usuario_id']   ?? null,
        'nome'  => $_SESSION['usuario_nome']  ?? '',
        'email' => $_SESSION['usuario_email'] ?? '',
    ];
}

function tentarLogin(string $email, string $senha): bool {
    iniciarSessao();
    $db   = getDB();
    $stmt = $db->prepare('SELECT id, nome, email, senha_hash, ativo FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([trim($email)]);
    $user = $stmt->fetch();

    if (!$user || !$user['ativo'] || !$user['senha_hash']) return false;
    if (!password_verify($senha, $user['senha_hash'])) return false;

    $_SESSION['usuario_id']    = $user['id'];
    $_SESSION['usuario_nome']  = $user['nome'];
    $_SESSION['usuario_email'] = $user['email'];
    session_regenerate_id(true);
    return true;
}

function fazerLogout(): void {
    iniciarSessao();
    $_SESSION = [];
    session_destroy();
}

function validarToken(string $token): ?array {
    if (strlen($token) < 32) return null;
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT id, nome, email FROM usuarios
          WHERE token = ? AND token_expira > NOW() AND ativo = 1 LIMIT 1'
    );
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

function definirSenha(int $userId, string $senha): void {
    $hash = password_hash($senha, PASSWORD_BCRYPT);
    $db   = getDB();
    $db->prepare('UPDATE usuarios SET senha_hash = ?, token = NULL, token_expira = NULL WHERE id = ?')
       ->execute([$hash, $userId]);
}

function verificarAdmin(string $senha): bool {
    return hash_equals(ADMIN_SENHA, $senha);
}
