<?php
session_start();

// Verifica se é um paciente logado
if (!isset($_SESSION['id']) || !isset($_SESSION['tipo_conta']) || $_SESSION['tipo_conta'] != 'paciente') {
    header("location: loginUser.html?voltar=financeiro.php");
    exit();
}

include "conexao.php";
garantir_estrutura($con);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id_fatura'])) {
    header("location: financeiro.php");
    exit();
}

// Verifica se o Access Token do Mercado Pago foi configurado (variável MP_ACCESS_TOKEN)
if (empty($mp_access_token)) {
    echo "<script>
            alert('Access Token do Mercado Pago não configurado!\\nCadastre a variável MP_ACCESS_TOKEN no Railway (aba Variables) ou no arquivo .env.');
            window.location.href = 'financeiro.php';
          </script>";
    exit();
}

$idUsuario = (int)$_SESSION['id'];
$idFatura = (int)$_POST['id_fatura'];

// Busca a fatura garantindo que ela pertence ao usuário logado e ainda não foi paga
$query = "SELECT f.*, u.Nome_User, u.Email_User, p.Nome_Plano
          FROM Faturas f
          INNER JOIN Cadastro_Users u ON f.Id_User = u.Id_User
          LEFT JOIN Planos p ON u.Id_Plano = p.Id_Plano
          WHERE f.Id_Fatura = ?
          AND f.Id_User = ?
          AND f.Status_Fatura <> 'Pago'
          LIMIT 1";

$resultado = $con->query($query, [$idFatura, $idUsuario]);

if (!$resultado || $resultado->num_rows == 0) {
    echo "<script>
            alert('Fatura não encontrada ou já paga.');
            window.location.href = 'financeiro.php';
          </script>";
    exit();
}

$fatura = $resultado->fetch_assoc();
$nomePlano = $fatura['Nome_Plano'] ?? 'Acesso+';

// Descobre a URL base do site automaticamente (funciona no Railway, atrás do proxy HTTPS, e localmente)
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$protocolo = $isHttps ? 'https' : 'http';
$diretorio = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$baseUrl = $protocolo . '://' . $_SERVER['HTTP_HOST'] . $diretorio;

// Monta a preferência do Checkout Pro (API de Preferences)
$payload = [
    "items" => [
        [
            "title" => "Mensalidade Acesso+ - Plano " . $nomePlano . " (" . $fatura['Mes_Referencia'] . ")",
            "quantity" => 1,
            "unit_price" => (float)$fatura['Valor'],
            "currency_id" => "BRL"
        ]
    ],
    "external_reference" => (string)$fatura['Id_Fatura'],
    "back_urls" => [
        "success" => $baseUrl . "/retorno_pagamento.php",
        "failure" => $baseUrl . "/financeiro.php?msg=falha",
        "pending" => $baseUrl . "/retorno_pagamento.php"
    ]
];

// O Mercado Pago só permite auto_return em URLs públicas HTTPS (fora de localhost)
$host = $_SERVER['HTTP_HOST'] ?? '';
if ($isHttps && stripos($host, 'localhost') === false && stripos($host, '127.0.0.1') === false) {
    $payload["auto_return"] = "approved";
}

// Chamada cURL para criar a preferência no Mercado Pago
$ch = curl_init("https://api.mercadopago.com/checkout/preferences");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $mp_access_token,
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$resposta = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$erroCurl = curl_error($ch);
curl_close($ch);

$dadosMP = json_decode((string)$resposta, true) ?: [];

// No padrão atual do Mercado Pago (APP_USR de teste), o init_point já abre o checkout de teste
$urlCheckout = $dadosMP['init_point'] ?? $dadosMP['sandbox_init_point'] ?? null;

if ($httpCode >= 200 && $httpCode < 300 && !empty($urlCheckout)) {
    header("Location: " . $urlCheckout);
    exit();
}

$mensagemErro = $dadosMP['message'] ?? ($erroCurl ?: 'Erro desconhecido ao comunicar com o Mercado Pago.');
error_log('[Acesso+] Mercado Pago (criar preferência) HTTP ' . $httpCode . ': ' . $mensagemErro);
$mensagemSegura = json_encode('Não foi possível iniciar o pagamento no Mercado Pago.' . "\n" . 'Detalhes: ' . $mensagemErro);
echo "<script>
        alert({$mensagemSegura});
        window.location.href = 'financeiro.php';
      </script>";
exit();
