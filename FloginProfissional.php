<?php
    session_start();

    include "conexao.php";

    if(isset($_POST['submit']) && !empty($_POST['CPF']) && !empty($_POST['senha']))
    {
        $CPF = $_POST['CPF'];
        $senha = $_POST['senha'];

        $comando = "SELECT * FROM Cadastro_Profissionais WHERE CPF_Profissional = ?";

        $resultado = $con->query($comando, [$CPF]);
        $usuario = $resultado ? $resultado->fetch_assoc() : null;

        // A senha é conferida pelo hash (password_verify), nunca comparada em texto puro no SQL
        if(!$usuario || !senha_confere($senha, $usuario['Senha_Profissional']))
            {
                unset($_SESSION['CPF']);
                unset($_SESSION['senha']);
                unset($_SESSION['nome']);
                unset($_SESSION['tipo_conta']);
                echo "<script>
                    alert('Usuário não encontrado!');
                    window.location.href = 'loginProfissional.html';
                    </script>";
                    exit();
            }
        else
        {
            // Cadastros antigos (senha sem criptografia) são convertidos para hash no primeiro login
            if (senha_precisa_hash($usuario['Senha_Profissional'])) {
                $con->query("UPDATE Cadastro_Profissionais SET Senha_Profissional = ? WHERE Id_Profissional = ?", [senha_hash($senha), (int)$usuario['Id_Profissional']]);
            }
            session_regenerate_id(true);


            $_SESSION['CPF'] = $CPF;
            $_SESSION['nome'] = $usuario['Nome_Profissional'];
            $_SESSION['id'] = $usuario['Id_Profissional'];
            $_SESSION['tipo_conta'] = 'profissional'; // Para evitar conflitos com login de paciente
            
            header("location: areaProfissional.php");
            exit();
        }
    }
    else
    {
        //Não deixa acessar a página via barra de pesquisa se não houver login
        header('location: loginProfissional.html');
        exit();
    }
?>