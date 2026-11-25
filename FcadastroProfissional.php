<?php
include "conexao.php";

$nome = trim((string)($_POST['nome'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$telefone = trim((string)($_POST['telefone'] ?? ''));
$registro = trim((string)($_POST['registro'] ?? ''));
$uf_registro = trim((string)($_POST['uf_registro'] ?? ''));
$especialidade = trim((string)($_POST['especialidade'] ?? ''));
$cep = preg_replace('/\D/', '', (string)($_POST['CEP'] ?? '')); // só números (evita URL inválida nas APIs de CEP)
$cpf = preg_replace('/\D/', '', (string)($_POST['CPF'] ?? '')); // grava só os números
$data_nasc = trim((string)($_POST['data_nasc'] ?? ''));
$genero = trim((string)($_POST['genero'] ?? ''));
$senha = (string)($_POST['senha'] ?? '');

// Confere os dados obrigatórios e as regras de senha também no servidor
$faltandoSenha = problemas_senha((string)$senha);
if (!cpf_valido($cpf) || !filter_var($email, FILTER_VALIDATE_EMAIL) || $faltandoSenha) {
    $motivo = !cpf_valido($cpf) ? 'CPF inválido. Confira os números digitados.'
        : ($faltandoSenha ? 'A senha precisa ' . implode(', ', $faltandoSenha) . '.' : 'Informe um e-mail válido.');
    echo "<script>alert(" . json_encode($motivo, JSON_UNESCAPED_UNICODE) . "); window.history.back();</script>";
    exit();
}

$comandoVerifica = "SELECT * FROM Cadastro_Profissionais WHERE REPLACE(REPLACE(CPF_Profissional, '.', ''), '-', '') = ? OR Email_Profissional = ? OR Registro_Profissional = ?";
$resultadoVerifica = $con->query($comandoVerifica, [$cpf, $email, $registro]);

if($resultadoVerifica && $resultadoVerifica->num_rows > 0)
{
    echo "<script>
            alert('Erro: Este CPF, E-mail ou Registro já está cadastrado em nosso sistema!');
            window.history.back();
          </script>";
    exit();
}
// API Gratuita OpenStreetMap (Nominatim) para pegar Lat e Lng
$urlDaApi = "https://nominatim.openstreetmap.org/search?postalcode={$cep}&country=brazil&format=json";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $urlDaApi);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 8);
curl_setopt($ch, CURLOPT_USERAGENT, "SistemaMedicoTCC/1.0");
$resposta = curl_exec($ch);
curl_close($ch);

$dadosGeolocalizacao = json_decode($resposta, true);

$latitude = "";
$longitude = "";

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

$urlViaCEP = "https://viacep.com.br/ws/{$cep}/json/";
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

// 3. Salvando tudo no Banco de Dados
$comando = "INSERT INTO Cadastro_Profissionais 
            (Nome_Profissional, Email_Profissional, CPF_Profissional, Registro_Profissional, UF_Registro_Profissional, CEP_Profissional, Numero_Profissional, Rua_Profissional, Bairro_Profissional, Cidade_Profissional, Latitude_Profissional, Longitude_Profissional, Especialidade_Profissional, Dta_Nasc_Profissional, Genero_Profissional, Num_Tel_Profissional, Senha_Profissional) 
            VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

if ($con->query($comando, [$nome, $email, $cpf, $registro, $uf_registro, $cep, $numero, $rua, $bairro, $cidade, $latitude, $longitude, $especialidade, $data_nasc, $genero, $telefone, senha_hash($senha)])) {
    echo "<script>
            alert('Profissional cadastrado com sucesso! Agora você já pode fazer login');
            window.location.href = 'loginProfissional.html';
          </script>";
} else {
    echo "<script>
            alert('Erro ao cadastrar. Verifique se o CPF, E-mail ou CRM já existem.');
            window.history.back();
          </script>";
}

?>
