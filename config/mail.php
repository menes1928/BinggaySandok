<?php
return [
    'host' => getenv('MAIL_HOST') ?: 'smtp.gmail.com',
    'port' => (int)(getenv('MAIL_PORT') ?: 587),
    'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls',
    'username' => getenv('MAIL_USERNAME') ?: 'sandokdummy@gmail.com', // set via env
    'password' => getenv('MAIL_PASSWORD') ?: 'jgpuickqdaeoakcc',      // set via env (Gmail App Password)
    'from_email' => getenv('MAIL_FROM') ?: (getenv('MAIL_USERNAME') ?: 'sandokdummy@gmail.com'),
    'from_name' => getenv('MAIL_FROM_NAME') ?: 'Sandok ni Binggay',
    'reply_to' => getenv('MAIL_REPLY_TO') ?: null,
];
