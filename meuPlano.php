<?php
    session_start();
    if(!isset($_SESSION['CPF']) || !isset($_SESSION['nome']) || !isset($_SESSION['tipo_conta']) || $_SESSION['tipo_conta'] != 'paciente') {
        header("location: loginUser.html?voltar=meuPlano.php");
        exit();
    }
    include "conexao.php";

    $nomeCompleto = $_SESSION['nome'];
    $primeiroNome = explode(' ', trim($nomeCompleto))[0];

	$IdUsuario = $_SESSION['id'] ?? 0;
	
    $comando = "SELECT u.*, p.Nome_Plano, p.Preco FROM Cadastro_Users u LEFT JOIN Planos p ON u.Id_Plano = p.Id_Plano WHERE u.Id_User = ?";
    $resultado = $con->query($comando, [(int)$IdUsuario]);
    
    if($resultado && $resultado->num_rows > 0) {
        $dadosUsuario = $resultado->fetch_assoc();
        $CPF = $dadosUsuario['CPF_User'];
        $dataNasc = $dadosUsuario['Dta_Nasc_User'];
        $dataNascFormatada = date("d/m/Y", strtotime($dataNasc));
        $plano = $dadosUsuario['Nome_Plano'] ?? "Sem Plano";
        $preco = $dadosUsuario['Preco'] ?? 0;
        $status = $dadosUsuario['Status_Pagamento'] ?? "Pendente";
    } else {
        $CPF = $_SESSION['CPF'] ?? "00000000000";
        $dataNascFormatada = "--/--/----";
        $plano = "Sem Plano";
        $preco = 0;
        $status = "Pendente";
    }

	$CPF = preg_replace('/\D/', '', (string)$CPF); // aceita CPF gravado com ou sem pontuação
	$cpfFormatado = substr($CPF, 0, 3) . "." . substr($CPF, 3, 3) . "." . substr($CPF, 6, 3) . "-" . substr($CPF, 9, 2);
	$matricula = sprintf('%09d', $IdUsuario);
    $statusClass = (strtolower($status) == 'ativo') ? 'confirmado' : 'pendente';
    $precoFormatado = number_format($preco, 2, ',', '.');
    $validade = date("m/Y", strtotime("+1 year"));

    // Troca de plano: lista os planos do banco e protege o formulário com um token (CSRF)
    $idPlanoAtual = (int)($dadosUsuario['Id_Plano'] ?? 0);
    $planosDisponiveis = [];
    $resPlanos = $con->query("SELECT Id_Plano, Nome_Plano, Preco FROM Planos ORDER BY Preco ASC");
    while ($resPlanos && ($linhaPlano = $resPlanos->fetch_assoc())) $planosDisponiveis[] = $linhaPlano;
    if (empty($_SESSION['csrf_plano'])) $_SESSION['csrf_plano'] = bin2hex(random_bytes(16));

    // Benefícios exibidos em cada plano (os mesmos da página pública planos.html)
    $beneficiosPlanos = [
        'essencial' => ['Consultas clínicas', 'Rede básica acessível', 'Carteirinha digital', 'Suporte por WhatsApp'],
        'plus'      => ['Tudo do plano Essencial', 'Consultas com especialistas', 'Terapias e reabilitação', 'Agendamento prioritário'],
        'premium'   => ['Tudo do plano Plus', 'Rede credenciada ampliada', 'Acompanhamento multidisciplinar', 'Check-up anual'],
    ];
    $msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso+ | Meu Plano</title>
    <link rel="stylesheet" href="css/areaUsuario.css">
</head>
<body>
    <?php include 'menuUsuario.php'; ?>

    <header class="area-header">
        <div class="header-content">
            <span class="eyebrow">Meu plano</span>
            <h1>Plano <?php echo htmlspecialchars($plano); ?></h1>
            <p>Confira os detalhes da sua cobertura e os dados da sua carteirinha.</p>
        </div>
    </header>

    <main>
        <?php if ($msg === 'plano_sem_cobranca'): ?>
            <div class="alerta-plano sucesso" role="status">
                Plano alterado para <strong><?php echo htmlspecialchars($plano); ?></strong>! A mensalidade deste mês já estava paga,
                então não há nada a pagar agora. O novo valor de R$ <?php echo $precoFormatado; ?> vale a partir da próxima fatura.
            </div>
        <?php elseif ($msg === 'plano_erro'): ?>
            <div class="alerta-plano erro" role="alert">
                Não foi possível alterar o plano. Atualize a página e tente novamente.
            </div>
        <?php endif; ?>

        <div class="painel">
            <!-- Dados fictícios para demonstração acadêmica do TCC. -->
            <article class="carteirinha">
                <div class="carteirinha-topo">
                    <span class="carteirinha-marca">Acesso+</span>
                    <span class="carteirinha-plano"><?php echo htmlspecialchars($plano); ?></span>
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

            <section class="detalhes-plano">
                <h2>Detalhes da assinatura</h2>

                <div class="detalhe-linha">
                    <span>Mensalidade</span>
                    <strong>R$ <?php echo $precoFormatado; ?>/mês</strong>
                </div>
                <div class="detalhe-linha">
                    <span>Forma de pagamento</span>
                    <strong>Mercado Pago (Cartão, PIX ou Boleto)</strong>
                </div>
                <div class="detalhe-linha">
                    <span>Início da vigência</span>
                    <strong>10/03/2024</strong>
                </div>
                <div class="detalhe-linha">
                    <span>Situação</span>
                    <span class="status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                </div>

                <div class="acao-plano-financeiro">
                    <?php if (strtolower($status) !== 'ativo'): ?>
                        <a href="pagarPlano.php" class="btn-regularizar-plano">Pagar mensalidade com Mercado Pago</a>
                    <?php else: ?>
                        <a href="financeiro.php" class="btn-ver-faturas">Ver histórico de faturas &rarr;</a>
                    <?php endif; ?>
                    <a href="#trocar-plano" class="btn-ver-faturas btn-trocar-plano">Trocar de plano &darr;</a>
                </div>
            </section>
        </div>

        <section class="trocar-plano" id="trocar-plano" aria-labelledby="titulo-trocar-plano">
            <h2 id="titulo-trocar-plano"><?php echo $idPlanoAtual ? 'Trocar de plano' : 'Escolha seu plano'; ?></h2>
            <p class="trocar-plano-intro">
                Ao escolher um plano você é levado ao Mercado Pago para pagar. Se a mensalidade deste mês já foi paga,
                você paga só a diferença para um plano mais caro; para um plano mais barato, o novo valor vale a partir do próximo mês.
            </p>

            <div class="planos-opcoes">
                <?php foreach ($planosDisponiveis as $opcao):
                    $ehAtual = (int)$opcao['Id_Plano'] === $idPlanoAtual;
                    $nomeOpcao = $opcao['Nome_Plano'];
                    $precoOpcao = number_format((float)$opcao['Preco'], 2, ',', '.');
                    $beneficios = $beneficiosPlanos[strtolower($nomeOpcao)] ?? [];
                ?>
                <article class="plano-opcao<?php echo $ehAtual ? ' atual' : ''; ?>">
                    <?php if ($ehAtual): ?><span class="selo-plano-atual">Seu plano</span><?php endif; ?>
                    <h3><?php echo htmlspecialchars($nomeOpcao); ?></h3>
                    <p class="plano-opcao-preco">R$ <?php echo $precoOpcao; ?> <span>/ mês</span></p>
                    <?php if ($beneficios): ?>
                        <ul>
                            <?php foreach ($beneficios as $b): ?><li><?php echo htmlspecialchars($b); ?></li><?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if ($ehAtual): ?>
                        <button type="button" class="btn-escolher-plano" disabled>Plano atual</button>
                    <?php else: ?>
                        <form action="alterarPlano.php" method="post"
                              onsubmit="return confirm(<?php echo htmlspecialchars(json_encode(($idPlanoAtual ? 'Trocar' : 'Assinar') . ' o plano ' . $nomeOpcao . ' (R$ ' . $precoOpcao . '/mês)?', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES); ?>);">
                            <input type="hidden" name="csrf" value="<?php echo $_SESSION['csrf_plano']; ?>">
                            <input type="hidden" name="id_plano" value="<?php echo (int)$opcao['Id_Plano']; ?>">
                            <button type="submit" class="btn-escolher-plano"><?php echo $idPlanoAtual ? 'Mudar para ' : 'Assinar '; ?><?php echo htmlspecialchars($nomeOpcao); ?></button>
                        </form>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="acesso-rapido">
            <h2>Cobertura do plano</h2>

            <div class="cards">
                <div class="card-content">
                    <img src="img/cuidados-de-saude.png" alt="Ícone de cuidados de saúde" class="card-img">
                    <div class="card-body">
                        <h3 class="card-title">Consultas com especialistas</h3>
                        <p class="card-description">Cardiologia, neurologia, ortopedia e mais, sem fila de espera longa.</p>
                    </div>
                </div>

                <div class="card-content">
                    <img src="img/pcd.png" alt="Ícone de terapias" class="card-img">
                    <div class="card-body">
                        <h3 class="card-title">Terapias e reabilitação</h3>
                        <p class="card-description">Fisioterapia, fonoaudiologia e terapia ocupacional com cobertura integral.</p>
                    </div>
                </div>

                <div class="card-content">
                    <img src="img/hospital.png" alt="Ícone de hospital" class="card-img">
                    <div class="card-body">
                        <h3 class="card-title">Rede acessível</h3>
                        <p class="card-description">Locais credenciados com estrutura adaptada e atendimento humanizado.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer style="text-align: center; padding: 20px; margin-top: 40px; color: #64748b; font-size: 0.85rem; border-top: 1px solid #e2e8f0;">
        <p>© 2026 Acesso+ | Painel do Beneficiário</p>
    </footer>
</body>
</html>
