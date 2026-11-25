<?php
    session_start();
    if(!isset($_SESSION['CPF']) || !isset($_SESSION['nome']) || !isset($_SESSION['tipo_conta']) || $_SESSION['tipo_conta'] != 'paciente')
        {
            unset($_SESSION['CPF']);
            unset($_SESSION['senha']);
            unset($_SESSION['nome']);
            unset($_SESSION['tipo_conta']);
            header("location: loginUser.html?voltar=areaUsuario.php");
            exit();
        }

    $nomeCompleto = $_SESSION['nome']; //Resgata o nome salvo na session no momento do login
    $primeiroNome = explode(' ', trim($nomeCompleto))[0]; //pega apenas o primeiro nome da pessoa
    include "conexao.php";

	$IdUsuario = $_SESSION['id'] ?? 0;
	
    $comando = "SELECT u.*, p.Nome_Plano FROM Cadastro_Users u LEFT JOIN Planos p ON u.Id_Plano = p.Id_Plano WHERE u.Id_User = ?";
    $resultado = $con->query($comando, [(int)$IdUsuario]);
    
    if($resultado && $resultado->num_rows > 0) {
        $dadosUsuario = $resultado->fetch_assoc();
        $CPF = $dadosUsuario['CPF_User'];
        $dataNasc = $dadosUsuario['Dta_Nasc_User'];
        $dataNascFormatada = date("d/m/Y", strtotime($dataNasc));
        $plano = !empty($dadosUsuario['Nome_Plano']) ? "Plano " . $dadosUsuario['Nome_Plano'] : "Sem Plano"; 
        $pagamentoPendente = !empty($dadosUsuario['Id_Plano']) && strtolower((string)$dadosUsuario['Status_Pagamento']) !== 'ativo';
        $semPlano = empty($dadosUsuario['Id_Plano']);
    } else {
        $CPF = $_SESSION['CPF'] ?? "00000000000";
        $dataNascFormatada = "--/--/----";
        $plano = "Sem Plano";
        $pagamentoPendente = false;
        $semPlano = true;
    }

	$CPF = preg_replace('/\D/', '', (string)$CPF); // aceita CPF gravado com ou sem pontuação
	$cpfFormatado = "***." . substr($CPF, 3, 3) . "." . substr($CPF, 6, 3) . "-**";
	$matricula = sprintf('%09d', $IdUsuario);
    $validade = date("m/Y", strtotime("+1 year"));
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso+ | Área do Beneficiário</title>
    <link rel="stylesheet" href="css/areaUsuario.css?v=3">
</head>
<body>
    <?php include 'menuUsuario.php'; ?>

    <header class="area-header">
        <div class="header-content">
            <span class="eyebrow">Área do beneficiário</span>
            <h1>Olá, <?php echo htmlspecialchars($nomeCompleto); ?> </h1>
            <p>Aqui está um resumo do seu plano e dos seus próximos atendimentos.</p>
        </div>
    </header>

    <main>
        <?php if ($semPlano): ?>
            <div class="alerta-plano aviso" role="status">
                Você ainda não tem um plano. <a href="meuPlano.php#trocar-plano"><strong>Escolha um plano</strong></a> para começar a agendar consultas.
            </div>
        <?php elseif ($pagamentoPendente): ?>
            <div class="alerta-plano aviso" role="status">
                Sua mensalidade está pendente. Para liberar os agendamentos,
                <a href="pagarPlano.php"><strong>pague com o Mercado Pago</strong></a> (cartão, PIX ou boleto).
            </div>
        <?php endif; ?>
        <div class="painel">
            <!-- Dados fictícios para demonstração acadêmica do TCC. -->
            <article class="carteirinha">
                <div class="carteirinha-topo">
                    <span class="carteirinha-marca">Acesso+</span>
                    <span class="carteirinha-plano"><?php echo $plano; ?></span>
                </div>
                <div class="carteirinha-info">
                    <p class="carteirinha-rotulo">Titular</p>
                    <h2 class="carteirinha-nome"><?php echo htmlspecialchars($nomeCompleto); ?></h2>
                    <div class="carteirinha-dados carteirinha-dados-wrap">
                        <div>
                            <p class="carteirinha-rotulo">CPF</p>
                            <p class="carteirinha-valor"><?php echo $cpfFormatado; ?></p>
                        </div>
                        <div>
                            <p class="carteirinha-rotulo">Matrícula</p>
                            <p class="carteirinha-valor"><?php echo $matricula; ?></p>
                        </div>
                        <div>
                            <p class="carteirinha-rotulo">Nascimento</p>
                            <p class="carteirinha-valor"><?php echo $dataNascFormatada; ?></p>
                        </div>
                        <div>
                            <p class="carteirinha-rotulo">Validade</p>
                            <p class="carteirinha-valor"><?php echo $validade; ?></p>
                        </div>
                    </div>
                </div>
            </article>

            <section class="agendamentos">
                <h2>Próximos agendamentos</h2>

                <?php
                // Busca os próximos agendamentos do usuário (limitado a 3 para o widget do painel)
                $queryAgendamentos = "SELECT c.Data_Consulta, c.Hora_Consulta, c.Status_Consulta, 
                                             p.Nome_Profissional, p.Especialidade_Profissional 
                                      FROM Consultas c 
                                      INNER JOIN Cadastro_Profissionais p ON c.Id_Profissional = p.Id_Profissional 
                                      WHERE c.Id_User = ? 
                                      AND c.Status_Consulta IN ('Pendente', 'Confirmado')
                                      AND c.Data_Consulta >= CURRENT_DATE
                                      ORDER BY c.Data_Consulta ASC, c.Hora_Consulta ASC LIMIT 3";
                
                $resultAgend = $con->query($queryAgendamentos, [(int)$IdUsuario]);

                if($resultAgend && $resultAgend->num_rows > 0) {
                    while($agend = $resultAgend->fetch_assoc()) {
                        $dataFormatada = date("d/m/Y", strtotime($agend['Data_Consulta']));
                        $horaFormatada = date("H:i", strtotime($agend['Hora_Consulta']));
                        $statusClass = (strtolower($agend['Status_Consulta']) == 'pendente') ? 'pendente' : 'confirmado';
                        
                        // Define o ícone baseado na especialidade (gambiarra visual)
                        $icone = "img/medico.png";
                        if(stripos($agend['Especialidade_Profissional'], 'fisioterapia') !== false) $icone = "img/pcd.png";
                        elseif(stripos($agend['Especialidade_Profissional'], 'clínica') !== false) $icone = "img/cuidados-de-saude.png";
                        ?>
                        
                        <div class="agendamento-item">
                            <img src="<?php echo $icone; ?>" alt="Ícone de consulta" class="agendamento-icone">
                            <div class="agendamento-info">
                                <h3><?php echo htmlspecialchars($agend['Especialidade_Profissional']); ?> — Dr(a). <?php echo htmlspecialchars($agend['Nome_Profissional']); ?></h3>
                                <p><?php echo $dataFormatada; ?> às <?php echo $horaFormatada; ?></p>
                            </div>
                            <span class="status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($agend['Status_Consulta']); ?></span>
                        </div>

                        <?php
                    }
                    echo '<a href="agendamentos.php" class="btn-ver-faturas" style="margin-top: 15px;">Ver todos &rarr;</a>';
                } else {
                    echo "<div class='empty-agendamentos'>";
                    echo "<p>Você não tem nenhuma consulta futura agendada.</p>";
                    echo '<a href="agendamentos.php" class="btn-agendar-empty">Agendar nova consulta</a>';
                    echo "</div>";
                }
                ?>
            </section>
        </div>

        <section class="acesso-rapido">
            <h2>Acesso rápido</h2>

            <div class="cards">
                <a href="redeCredenciada.php" class="card-content card-link">
                    <img src="img/hospital.png" alt="" class="card-img">
                    <div class="card-body">
                        <h3 class="card-title">Rede credenciada</h3>
                        <p class="card-description">Encontre locais acessíveis e qualificados perto de você.</p>
                    </div>
                </a>

                <a href="agendamentos.php" class="card-content card-link">
                    <img src="img/medico.png" alt="" class="card-img">
                    <div class="card-body">
                        <h3 class="card-title">Agendar consulta</h3>
                        <p class="card-description">Escolha um profissional especializado e um horário livre.</p>
                    </div>
                </a>

                <a href="meuPlano.php" class="card-content card-link">
                    <img src="img/pcd.png" alt="" class="card-img">
                    <div class="card-body">
                        <h3 class="card-title">Meu plano</h3>
                        <p class="card-description">Veja a carteirinha, as coberturas e a situação do pagamento.</p>
                    </div>
                </a>

                <a href="contato.php" class="card-content card-link">
                    <img src="img/cuidados-de-saude.png" alt="" class="card-img">
                    <div class="card-body">
                        <h3 class="card-title">Suporte</h3>
                        <p class="card-description">Fale com a nossa equipe pelo Fale Conosco.</p>
                    </div>
                </a>
            </div>
        </section>
    </main>

    <footer class="rodape-painel">
        <p>© 2026 Acesso+ | Painel do Beneficiário</p>
    </footer>
</body>
</html>

