<?php
session_start();
include "conexao.php";  

$nome =trim((string)($_POST['nome'] ?? ''));
$email =trim((string)($_POST['email'] ?? ''));
$telefone =trim((string)($_POST['telefone'] ?? ''));
$CPF = preg_replace('/\D/', '', (string)($_POST['CPF'] ?? '')); // grava só os números
$CEP = preg_replace('/\D/', '', (string)($_POST['CEP'] ?? '')); // só números (evita URL inválida nas APIs de CEP)
$data_nasc =trim((string)($_POST['data_nasc'] ?? ''));
$genero =trim((string)($_POST['genero'] ?? ''));
$est_civil =trim((string)($_POST['est_civil'] ?? ''));
$senha = (string)($_POST['senha'] ?? '');
// A senha é gravada criptografada (password_hash) no momento do INSERT

// Confere os dados obrigatórios e as regras de senha também no servidor
$faltandoSenha = problemas_senha((string)$senha);
if (!cpf_valido($CPF) || !filter_var($email, FILTER_VALIDATE_EMAIL) || $faltandoSenha) {
    $motivo = !cpf_valido($CPF) ? 'CPF inválido. Confira os números digitados.'
        : ($faltandoSenha ? 'A senha precisa ' . implode(', ', $faltandoSenha) . '.' : 'Informe um e-mail válido.');
    echo "<script>alert(" . json_encode($motivo, JSON_UNESCAPED_UNICODE) . "); window.history.back();</script>";
    exit();
}

// Verifica se o CPF ou E-mail já estão cadastrados
$comandoVerifica = "SELECT * FROM Cadastro_Users WHERE CPF_User = ? OR REPLACE(REPLACE(CPF_User, '.', ''), '-', '') = ? OR Email_User = ?";
$resultadoVerifica = $con->query($comandoVerifica, [$CPF, preg_replace('/\D/', '', $CPF), $email]);

if($resultadoVerifica && $resultadoVerifica->num_rows > 0)
{
    echo "<script>
            alert('Erro: Este CPF ou E-mail já está cadastrado em nosso sistema!');
            window.history.back();
          </script>";
    exit();
}

// API Gratuita OpenStreetMap (Nominatim) para pegar Lat e Lng pelo CEP sem precisar de cartão
$urlDaApi = "https://nominatim.openstreetmap.org/search?postalcode={$CEP}&country=brazil&format=json";

$latitude = "";
$longitude = "";

// Usando cURL com User-Agent (obrigatório para essa API gratuita)
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $urlDaApi);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 8);
curl_setopt($ch, CURLOPT_USERAGENT, "SistemaMedicoTCC/1.0"); // A API exige identificação
$resposta = curl_exec($ch);
curl_close($ch);

$dadosGeolocalizacao = json_decode($resposta, true);

// A API retorna um array. Se achar o CEP, o índice [0] tem lat e lon
if (!empty($dadosGeolocalizacao)) {
    $latitude = $dadosGeolocalizacao[0]['lat'];
    $longitude = $dadosGeolocalizacao[0]['lon'];
}

// -----------------------------------------------------------
// NOVO: Consumindo a API do ViaCEP para descobrir a Rua e Bairro
// -----------------------------------------------------------
$rua = "";
$bairro = "";
$cidade = "";
$numero = $_POST['numero'] ?? 'S/N';

$urlViaCEP = "https://viacep.com.br/ws/{$CEP}/json/";
$chCep = curl_init();
curl_setopt($chCep, CURLOPT_URL, $urlViaCEP);
curl_setopt($chCep, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($chCep, CURLOPT_TIMEOUT, 8);
$respostaCep = curl_exec($chCep);
curl_close($chCep);

$dadosEndereco = json_decode($respostaCep, true);

if (!isset($dadosEndereco['erro']) && !empty($dadosEndereco)) {
    $rua = $dadosEndereco['logradouro'];
    $bairro = $dadosEndereco['bairro'];
    $cidade = $dadosEndereco['localidade'];
}

// -----------------------------------------------------------
// NOVO: Coletando o plano
// -----------------------------------------------------------
$id_plano = (int)($_POST['id_plano'] ?? 1);
if ($id_plano <= 0) $id_plano = 1; // Padrão Essencial se algo falhar

$comando = "INSERT INTO Cadastro_Users 
    (Nome_User, Dta_Nasc_User, Genero_User, CPF_User, CEP_User, Numero_User, Rua_User, Bairro_User, Cidade_User, Latitude_User, Longitude_User, Email_User, Senha_User, Num_Tel_User, Est_Civil_User, Id_Plano, Status_Pagamento) 
    VALUES 
    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendente')
    RETURNING Id_User";

$resulta = $con->query($comando, [$nome, $data_nasc, $genero, $CPF, $CEP, $numero, $rua, $bairro, $cidade, $latitude, $longitude, $email, senha_hash($senha), $telefone, $est_civil, $id_plano]);

if ($resulta && $resulta->num_rows > 0) {
    // Já entra logado e vai direto ao Mercado Pago pagar a primeira mensalidade (começa o plano)
    session_regenerate_id(true);
    $_SESSION['CPF'] = $CPF;
    $_SESSION['nome'] = $nome;
    $_SESSION['id'] = (int)$resulta->fetch_assoc()['Id_User'];
    $_SESSION['tipo_conta'] = 'paciente';
    echo "<script>
            alert('Cadastro realizado com sucesso! Agora você será levado ao Mercado Pago para pagar a primeira mensalidade e ativar o seu plano.');
            window.location.href = 'pagarPlano.php';
          </script>";
} else {
    // Se houver algum erro na gravação do banco
    echo "<script>
            alert('Erro ao realizar o cadastro. Tente novamente.');
            window.history.back();
          </script>";
}

?>