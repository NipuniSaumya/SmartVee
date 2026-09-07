<?php
/**
 * Copy this file to email_config.php and fill in your SMTP details.
 */
return [
    'enabled' => true,
    'method' => 'smtp', // smtp or mail
    'from_name' => 'SmartVee Auto Parts',
    'from_email' => 'billing@smartvee.com',
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_user' => 'your-email@gmail.com',
    'smtp_pass' => 'your-app-password',
    'smtp_secure' => 'tls', // tls or ssl
];
