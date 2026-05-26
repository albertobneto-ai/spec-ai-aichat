-- ============================================================
--  AI Chat — Schema do Banco de Dados
--  Execute este arquivo no MySQL antes de subir o projeto.
--  Hostinger: hPanel > Banco de Dados > phpMyAdmin > SQL
-- ============================================================

CREATE TABLE IF NOT EXISTS usuarios (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(100)  NOT NULL,
    email       VARCHAR(150)  NOT NULL UNIQUE,
    senha_hash  VARCHAR(255)  DEFAULT NULL,        -- NULL até o usuário definir a senha
    token       VARCHAR(64)   DEFAULT NULL,        -- token de primeiro acesso
    token_expira DATETIME     DEFAULT NULL,        -- expiração do token (48h)
    ativo       TINYINT(1)    NOT NULL DEFAULT 1,  -- 0 = desativado
    criado_em   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
