<?php
$url = 'http://31.220.31.26:8069/jsonrpc';
$db = 'politef';
$username = 'odoo';
$password = 'odoo';

$data = [
    "jsonrpc" => "2.0",
    "method" => "call",
    "params" => [
        "service" => "object",
        "method" => "execute_kw",
        "args" => [
            $db,
            2,
            $username,
            "op.student",
            "search_read",
            [[["gr_no", "=", "A14965"], ["curp", "=", "VAHS090507MCHLRHA2"]]],
            ["fields" => ["id", "name", "gr_no", "curp"]]
        ]
    ],
    "id" => 1
];

$options = [
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-type: application/json",
        'content' => json_encode($data)
    ]
];

$context = stream_context_create($options);
$result = file_get_contents($url, false, $context);
echo "<pre>";
print_r(json_decode($result, true));
echo "</pre>";
?>
