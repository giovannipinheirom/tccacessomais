<?php
    session_start();
    if(!isset($_SESSION['CPF']) || !isset($_SESSION['nome']) || !isset($_SESSION['tipo_conta']) || $_SESSION['tipo_conta'] != 'paciente') {
        header("location: loginUser.html?voltar=financeiro.php");
        exit();
    }

    include "conexao.php";
    garantir_estrutura($con); // cria a tabela Faturas no Supabase se ainda não existir

    $nomeCompleto = $_SESSION['nome'];
    $primeiroNome = explode(' ', trim($nomeCompleto))[0];
    $IdUsuario = (int)($_SESSION['id'] ?? 0);

    // 1. Busca os dados do usuário e do seu plano
    $comandoUsuario = "SELECT u.*, p.Nome_Plano, p.Preco
                       FROM Cadastro_Users u
                       LEFT JOIN Planos p ON u.Id_Plano = p.Id_Plano
                       WHERE u.Id_User = ?";
    $resUsuario = $con->query($comandoUsuario, [$IdUsuario]);

    $planoNome = "Sem Plano";
    $precoPlano = 0;
    $statusPagamento = "Pendente";

    if ($resUsuario && $resUsuario->num_rows > 0) {
        $dadosUsuario = $resUsuario->fetch_assoc();
        $planoNome = $dadosUsuario['Nome_Plano'] ?? "Sem Plano";
        $precoPlano = (float)($dadosUsuario['Preco'] ?? 0);
        $statusPagamento = $dadosUsuario['Status_Pagamento'] ?? "Pendente";
    }

    // 2. LAZY LOADING (Geração Sob Demanda da Mensalidade do Mês Atual)
    // Verifica se o paciente já possui fatura para o mês/ano vigente (MM/AAAA)
    $mesAtual = date('m/Y');
    if ($precoPlano > 0) {
        $checkFatura = $con->query(
            "SELECT Id_Fatura, Status_Fatura FROM Faturas WHERE Id_User = ? AND Mes_Referencia = ? LIMIT 1",
            [$IdUsuario, $mesAtual]
        );

        if ($checkFatura && $checkFatura->num_rows == 0) {
            // Virou o mês (ou primeiro acesso): cria a fatura do mês atual com vencimento para daqui a 5 dias
            $vencimento = date('Y-m-d', strtotime('+5 days'));
            $criada = $con->query(
                "INSERT INTO Faturas (Id_User, Mes_Referencia, Valor, Data_Vencimento, Status_Fatura)
                 VALUES (?, ?, ?, ?, 'Pendente')
                 ON CONFLICT (Id_User, Mes_Referencia) DO NOTHING",
                [$IdUsuario, $mesAtual, $precoPlano, $vencimento]
            );

            // Marca o status do plano como Pendente até a mensalidade do mês atual ser paga
            if ($criada && $criada->num_rows > 0) {
                $con->query("UPDATE Cadastro_Users SET Status_Pagamento = 'Pendente' WHERE Id_User = ?", [$IdUsuario]);
                $statusPagamento = "Pendente";
            }
        }
    }

    // 3. Busca o histórico completo de faturas do paciente
    $resultFaturas = $con->query("SELECT * FROM Faturas WHERE Id_User = ? ORDER BY Id_Fatura DESC", [$IdUsuario]);

    $statusPlanoClass = (strtolower($statusPagamento) == 'ativo') ? 'confirmado' : 'pendente';
    $precoFormatado = number_format($precoPlano, 2, ',', '.');
    $msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso+ | Financeiro</title>
    <link rel="stylesheet" href="css/areaUsuario.css">
    <link rel="stylesheet" href="css/financeiro.css">
</head>
<body>
    <?php include 'menuUsuario.php'; ?>

    <header class="area-header">
        <div class="header-content">
            <span class="eyebrow">Financeiro</span>
            <h1>Minhas faturas e mensalidades</h1>
            <p>Acompanhe seus pagamentos e regularize sua mensalidade com segurança via Mercado Pago.</p>
        </div>
    </header>

    <main>
        <div class="financeiro-container">

            <?php if ($msg === 'aprovado'): ?>
                <div class="alerta-pagamento sucesso" role="status">
                    Pagamento aprovado com sucesso! Sua fatura foi quitada e seu plano Acesso+ já está Ativo para agendamentos.
                </div>
            <?php elseif ($msg === 'pendente'): ?>
                <div class="alerta-pagamento aviso" role="status">
                    Seu pagamento está em processamento pelo Mercado Pago. Assim que for aprovado, seu plano será liberado automaticamente.
                </div>
            <?php elseif ($msg === 'falha'): ?>
                <div class="alerta-pagamento erro" role="alert">
                    O pagamento não foi concluído ou foi recusado. Você pode tentar novamente clicando no botão de pagamento abaixo.
                </div>
            <?php elseif ($msg === 'erro'): ?>
                <div class="alerta-pagamento erro" role="alert">
                    Não foi possível validar o retorno do pagamento. Verifique os dados ou tente novamente.
                </div>
            <?php endif; ?>

            <section class="resumo-financeiro">
                <div class="card-resumo">
                    <span class="rotulo">Plano Atual</span>
                    <p class="valor-destaque">Plano <?php echo htmlspecialchars($planoNome); ?></p>
                </div>

                <div class="card-resumo">
                    <span class="rotulo">Valor Mensal</span>
                    <p class="valor-destaque">R$ <?php echo $precoFormatado; ?></p>
                </div>

                <div class="card-resumo">
                    <span class="rotulo">Situação do Plano</span>
                    <span class="status <?php echo $statusPlanoClass; ?>"><?php echo htmlspecialchars($statusPagamento); ?></span>
                </div>
            </section>

            <section class="box-faturas">
                <h2>Histórico de Faturas</h2>
                <p class="subtitulo-faturas">A fatura do mês vigente é gerada automaticamente todo início de mês.</p>

                <?php if ($resultFaturas && $resultFaturas->num_rows > 0): ?>
                    <div class="lista-faturas">
                        <?php
                        $hoje = date('Y-m-d');
                        while ($fatura = $resultFaturas->fetch_assoc()):
                            $vencFormatado = date("d/m/Y", strtotime($fatura['Data_Vencimento']));
                            $valorFatura = number_format((float)$fatura['Valor'], 2, ',', '.');
                            $statusFatura = $fatura['Status_Fatura'];
                            $statusFaturaLower = strtolower((string)$statusFatura);

                            if ($statusFaturaLower == 'pago') {
                                $badgeClass = 'confirmado';
                                $textoStatus = 'Pago';
                            } elseif ($fatura['Data_Vencimento'] < $hoje) {
                                $badgeClass = 'atrasada';
                                $textoStatus = 'Em Atraso';
                            } else {
                                $badgeClass = 'pendente';
                                $textoStatus = 'Pendente';
                            }
                        ?>
                            <div class="fatura-item">
                                <div class="fatura-info">
                                    <h3>Mensalidade — Referência <?php echo htmlspecialchars($fatura['Mes_Referencia']); ?></h3>
                                    <p><strong>Vencimento:</strong> <?php echo $vencFormatado; ?></p>
                                    <?php if ($statusFaturaLower == 'pago' && !empty($fatura['Data_Pagamento'])): ?>
                                        <p class="comprovante-pago">
                                            Pago em <?php echo date("d/m/Y H:i", strtotime($fatura['Data_Pagamento'])); ?>
                                            <?php if (!empty($fatura['Id_Pagamento_MP'])): ?>
                                                | Comprovante MP #<?php echo htmlspecialchars($fatura['Id_Pagamento_MP']); ?>
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <div class="fatura-acoes">
                                    <span class="fatura-valor">R$ <?php echo $valorFatura; ?></span>
                                    <span class="status <?php echo $badgeClass; ?>"><?php echo $textoStatus; ?></span>

                                    <?php if ($statusFaturaLower != 'pago'): ?>
                                        <form action="criar_pagamento.php" method="POST">
                                            <input type="hidden" name="id_fatura" value="<?php echo (int)$fatura['Id_Fatura']; ?>">
                                            <button type="submit" class="btn-pagar-mp">Pagar com Mercado Pago</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-faturas">
                        <p>Nenhuma fatura encontrada para a sua conta.</p>
                    </div>
                <?php endif; ?>
            </section>

        </div>
    </main>

    <footer class="rodape-painel">
        <p>© 2026 Acesso+ | Painel do Beneficiário</p>
    </footer>
</body>
</html>
