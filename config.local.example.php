<?php
return [
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'your_database_name',
    'DB_USER' => 'your_database_user',
    'DB_PASS' => 'your_database_password',

    // Generate with:
    // php -r "echo password_hash('your-admin-password', PASSWORD_DEFAULT), PHP_EOL;"
    'ADMIN_PASSWORD_HASH' => '',

    'SHOP_NAME' => 'Tabacoudon',
    'SHOP_TAGLINE' => 'Votre spécialiste e-liquid OUDON',
    'WHATSAPP_NUMBER' => '33612345678',

    'GROQ_API_KEY' => '',
    'GEMINI_API_KEY' => '',
];
