<?php
/**
 * Regras de cobrança do Acesso+ (faturas + Mercado Pago Checkout Pro).
 * Usado pelo Financeiro, pela troca de plano (Meu plano), pelo início do plano após o cadastro e pela API do app.
 * Precisa que o conexao.php já tenha sido incluído.
 */

/** Fatura da mensalidade do mês atual. Cria se ainda não existir (vencimento em 5 dias). */
function fatura_do_mes(Banco $con, int $idUser): ?array
{
    $mesAtual = date('m/Y');
    $r = $con->query(
        "SELECT f.* FROM Faturas f WHERE f.Id_User = ? AND f.Mes_Referencia = ? AND f.Descricao IS NULL LIMIT 1",
        [$idUser, $mesAtual]
    );
    if ($r && $r->num_rows > 0) return $r->fetch_assoc();

    $plano = $con->query(
        "SELECT p.Preco FROM Cadastro_Users u INNER JOIN Planos p ON p.Id_Plano = u.Id_Plano WHERE u.Id_User = ?",
        [$idUser]
    );
    $preco = $plano ? (float)($plano->fetch_assoc()['Preco'] ?? 0) : 0;
    if ($preco <= 0) return null; // paciente sem plano: nada a cobrar

    $criada = $con->query(
        "INSERT INTO Faturas (Id_User, Mes_Referencia, Valor, Data_Vencimento, Status_Fatura)
         VALUES (?, ?, ?, ?, 'Pendente')
         ON CONFLICT (Id_User, Mes_Referencia) WHERE Descricao IS NULL DO NOTHING
         RETURNING *",
        [$idUser, $mesAtual, $preco, date('Y-m-d', strtotime('+5 days'))]
    );
    if ($criada && $criada->num_rows > 0) {
        // Mensalidade nova em aberto: o plano fica Pendente até ela ser paga
        $con->query("UPDATE Cadastro_Users SET Status_Pagamento = 'Pendente' WHERE Id_User = ?", [$idUser]);
        return $criada->fetch_assoc();
    }
    $r = $con->query("SELECT * FROM Faturas WHERE Id_User = ? AND Mes_Referencia = ? AND Descricao IS NULL LIMIT 1", [$idUser, $mesAtual]);
    return $r ? $r->fetch_assoc() : null;
}

/** Fatura em aberto mais antiga do paciente. */
function fatura_em_aberto(Banco $con, int $idUser): ?array
{
    $r = $con->query(
        "SELECT * FROM Faturas WHERE Id_User = ? AND Status_Fatura <> 'Pago'
         ORDER BY Data_Vencimento ASC, Id_Fatura ASC LIMIT 1",
        [$idUser]
    );
    return ($r && $r->num_rows > 0) ? $r->fetch_assoc() : null;
}

/**
 * Troca o plano do paciente e prepara a cobrança.
 * Retorna ['situacao' => ..., 'plano' => [...], 'id_fatura' => int|null]
 *   'pagar'        -> existe uma fatura para pagar no Mercado Pago (id_fatura)
 *   'sem_cobranca' -> o mês já está pago com valor igual ou maior; o novo preço vale a partir do próximo mês
 *   'erro'         -> plano ou paciente inválido
 */
function trocar_plano(Banco $con, int $idUser, int $idPlano): array
{
    $r = $con->query("SELECT Id_Plano, Nome_Plano, Preco FROM Planos WHERE Id_Plano = ?", [$idPlano]);
    $plano = $r ? $r->fetch_assoc() : null;
    $u = $con->query("SELECT Id_Plano FROM Cadastro_Users WHERE Id_User = ?", [$idUser]);
    $usuario = $u ? $u->fetch_assoc() : null;
    if (!$plano || !$usuario) return ['situacao' => 'erro', 'plano' => null, 'id_fatura' => null];

    $mesAtual = date('m/Y');
    $con->query("UPDATE Cadastro_Users SET Id_Plano = ? WHERE Id_User = ?", [$idPlano, $idUser]);
    $preco = (float)$plano['Preco'];

    // Diferenças de troca ainda não pagas deste mês são recalculadas do zero
    $con->query(
        "DELETE FROM Faturas WHERE Id_User = ? AND Mes_Referencia = ? AND Descricao IS NOT NULL AND Status_Fatura <> 'Pago'",
        [$idUser, $mesAtual]
    );

    $mensalidade = fatura_do_mes($con, $idUser);
    if ($mensalidade && strtolower((string)$mensalidade['Status_Fatura']) !== 'pago') {
        // Mensalidade do mês ainda em aberto: passa a ter o valor do novo plano.
        // Um boleto/PIX gerado antes com o valor antigo deixa de valer (o retorno confere o valor pago).
        $con->query("UPDATE Faturas SET Valor = ?, Id_Pagamento_MP = NULL WHERE Id_Fatura = ?", [$preco, (int)$mensalidade['Id_Fatura']]);
        $con->query("UPDATE Cadastro_Users SET Status_Pagamento = 'Pendente' WHERE Id_User = ?", [$idUser]);
        return ['situacao' => 'pagar', 'plano' => $plano, 'id_fatura' => (int)$mensalidade['Id_Fatura']];
    }

    // Mensalidade do mês já paga: cobra só a diferença se o novo plano for mais caro
    $pago = $con->query(
        "SELECT COALESCE(SUM(Valor), 0) AS total FROM Faturas WHERE Id_User = ? AND Mes_Referencia = ? AND Status_Fatura = 'Pago'",
        [$idUser, $mesAtual]
    );
    $totalPago = $pago ? (float)($pago->fetch_assoc()['total'] ?? 0) : 0;
    $diferenca = round($preco - $totalPago, 2);

    if ($diferenca <= 0) {
        // Ex.: troca para um plano mais barato. Nada a cobrar agora; garante que o plano continua Ativo
        $emAberto = fatura_em_aberto($con, $idUser);
        if (!$emAberto) $con->query("UPDATE Cadastro_Users SET Status_Pagamento = 'Ativo' WHERE Id_User = ?", [$idUser]);
        return ['situacao' => 'sem_cobranca', 'plano' => $plano, 'id_fatura' => null];
    }

    $nova = $con->query(
        "INSERT INTO Faturas (Id_User, Mes_Referencia, Valor, Data_Vencimento, Status_Fatura, Descricao)
         VALUES (?, ?, ?, ?, 'Pendente', ?) RETURNING Id_Fatura",
        [$idUser, $mesAtual, $diferenca, date('Y-m-d', strtotime('+5 days')),
         'Diferença para o plano ' . $plano['Nome_Plano']]
    );
    $idFatura = $nova ? (int)($nova->fetch_assoc()['Id_Fatura'] ?? 0) : 0;
    if ($idFatura <= 0) return ['situacao' => 'erro', 'plano' => $plano, 'id_fatura' => null];
    $con->query("UPDATE Cadastro_Users SET Status_Pagamento = 'Pendente' WHERE Id_User = ?", [$idUser]);
    return ['situacao' => 'pagar', 'plano' => $plano, 'id_fatura' => $idFatura];
}

/**
 * Cria a preferência do Checkout Pro no Mercado Pago para uma fatura do paciente.
 * Retorna ['url' => link do checkout] ou ['erro' => mensagem].
 */
function checkout_mercado_pago(Banco $con, int $idFatura, int $idUser): array
{
    global $mp_access_token;
    if (empty($mp_access_token)) {
        return ['erro' => 'Access Token do Mercado Pago não configurado. Cadastre a variável MP_ACCESS_TOKEN no Railway (aba Variables).'];
    }

    $r = $con->query(
        "SELECT f.*, p.Nome_Plano FROM Faturas f
         INNER JOIN Cadastro_Users u ON f.Id_User = u.Id_User
         LEFT JOIN Planos p ON u.Id_Plano = p.Id_Plano
         WHERE f.Id_Fatura = ? AND f.Id_User = ? AND f.Status_Fatura <> 'Pago' LIMIT 1",
        [$idFatura, $idUser]
    );
    if (!$r || $r->num_rows === 0) return ['erro' => 'Fatura não encontrada ou já paga.'];
    $fatura = $r->fetch_assoc();

    $titulo = !empty($fatura['Descricao'])
        ? 'Acesso+ - ' . $fatura['Descricao'] . ' (' . $fatura['Mes_Referencia'] . ')'
        : 'Mensalidade Acesso+ - Plano ' . ($fatura['Nome_Plano'] ?? 'Acesso+') . ' (' . $fatura['Mes_Referencia'] . ')';

    $baseUrl = url_base_site();
    $payload = [
        'items' => [[
            'title' => $titulo,
            'quantity' => 1,
            'unit_price' => (float)$fatura['Valor'],
            'currency_id' => 'BRL',
        ]],
        'external_reference' => (string)$fatura['Id_Fatura'],
        'back_urls' => [
            'success' => $baseUrl . '/retorno_pagamento.php',
            'failure' => $baseUrl . '/financeiro.php?msg=falha',
            'pending' => $baseUrl . '/retorno_pagamento.php',
        ],
    ];
    // O Mercado Pago só aceita auto_return com URLs públicas HTTPS (fora de localhost)
    if (stripos($baseUrl, 'https://') === 0 && stripos($baseUrl, 'localhost') === false && stripos($baseUrl, '127.0.0.1') === false) {
        $payload['auto_return'] = 'approved';
    }

    $ch = curl_init('https://api.mercadopago.com/checkout/preferences');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $mp_access_token, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);
    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erroCurl = curl_error($ch);
    curl_close($ch);

    $dados = json_decode((string)$resposta, true) ?: [];
    $url = $dados['init_point'] ?? $dados['sandbox_init_point'] ?? null;
    if ($httpCode >= 200 && $httpCode < 300 && !empty($url)) return ['url' => $url];

    $mensagem = $dados['message'] ?? ($erroCurl ?: 'Erro desconhecido ao comunicar com o Mercado Pago.');
    error_log('[Acesso+] Mercado Pago (criar preferência) HTTP ' . $httpCode . ': ' . $mensagem);
    return ['erro' => 'Não foi possível iniciar o pagamento no Mercado Pago. Detalhes: ' . $mensagem];
}

/** Mostra um alerta e volta para outra página (usado quando o checkout não pode ser aberto). */
function alerta_e_volta(string $mensagem, string $pagina): void
{
    echo '<script>alert(' . json_encode($mensagem, JSON_UNESCAPED_UNICODE) . '); window.location.href = '
        . json_encode($pagina) . ';</script>';
    exit();
}
