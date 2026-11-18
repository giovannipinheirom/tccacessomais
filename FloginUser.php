<?php
    session_start();

    include "conexao.php";

    if(isset($_POST['submit']) && !empty($_POST['CPF']) && !empty($_POST['senha']))
    {
        $CPF = $_POST['CPF'];
        $senha = $_POST['senha'];

        $comando = "SELECT * FROM Cadastro_Users WHERE CPF_User = ?";

        $resultado = $con->query($comando, [$CPF]);
        $usuario = $resultado ? $resultado->fetch_assoc() : null;

        // A senha é conferida pelo hash (password_verify), nunca comparada em texto puro no SQL
        if(!$usuario || !senha_confere($senha, $usuario['Senha_User']))
            {
                unset($_SESSION['CPF']);
                unset($_SESSION['senha']);
                unset($_SESSION['nome']);
                echo "<script>
                    alert('Usuário não encontrado!');
                    window.location.href = 'loginUser.html';
                    </script>";
                    exit();
            }
        else
        {
            // Cadastros antigos (senha sem criptografia) são convertidos para hash no primeiro login
            if (senha_precisa_hash($usuario['Senha_User'])) {
                $con->query("UPDATE Cadastro_Users SET Senha_User = ? WHERE Id_User = ?", [senha_hash($senha), (int)$usuario['Id_User']]);
            }
            session_regenerate_id(true);


            $_SESSION['CPF'] = $CPF;
            $_SESSION['nome'] = $usuario['Nome_User'];
            $_SESSION['id'] = $usuario['Id_User'];
            $_SESSION['tipo_conta'] = 'paciente'; // NOVO: Define a permissão correta
            
            header("location: areaUsuario.php");
            exit();
        }
    }
    else
    {
        //Não deixa acessar a página via barra de pesquisa se não houver login
        header('location: loginUser.html');
        exit();
    }
?>