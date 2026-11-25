<?php
    session_start();
    if(!isset($_SESSION['CPF']) || !isset($_SESSION['nome']) || !isset($_SESSION['tipo_conta']) || $_SESSION['tipo_conta'] != 'paciente') {
        header("location: loginUser.html?voltar=redeCredenciada.php");
        exit();
    }
    $nomeCompleto = $_SESSION['nome'];
    $primeiroNome = explode(' ', trim($nomeCompleto))[0];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso+ | Rede Credenciada</title>
    <link rel="stylesheet" href="css/areaUsuario.css">
    <!-- Mapa com OpenStreetMap + Leaflet (gratuito, não precisa de chave de API) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body>
    <?php include 'menuUsuario.php'; ?>

    <header class="area-header">
        <div class="header-content">
            <span class="eyebrow">Rede credenciada</span>
            <h1>Locais e profissionais</h1>
            <p>Todos os locais credenciados possuem estrutura adaptada e atendimento acessível.</p>
        </div>
    </header>

    <main>
        <section class="acesso-rapido mt-0">
            <h2>Mapa da Rede Credenciada</h2>

            <!-- Div onde o mapa (OpenStreetMap) será renderizado -->
            <div id="map" class="map-container"></div>

            <?php
            // Busca todos os profissionais cadastrados que possuem Latitude e Longitude
            include "conexao.php";
            $queryMedicos = "SELECT Nome_Profissional, Especialidade_Profissional, Rua_Profissional, Numero_Profissional, Latitude_Profissional, Longitude_Profissional FROM Cadastro_Profissionais WHERE Latitude_Profissional IS NOT NULL AND Longitude_Profissional IS NOT NULL";
            $resultadoMedicos = $con->query($queryMedicos);
            
            $medicosArray = [];
            if($resultadoMedicos && $resultadoMedicos->num_rows > 0) {
                while($row = $resultadoMedicos->fetch_assoc()) {
                    $medicosArray[] = $row;
                }
            }
            ?>

            <script>
                // Passa os dados do PHP (Banco de Dados) para o JavaScript
                const locaisMedicos = <?php echo json_encode($medicosArray); ?>
                    .map(m => ({ ...m, lat: parseFloat(m.Latitude_Profissional), lng: parseFloat(m.Longitude_Profissional) }))
                    .filter(m => !isNaN(m.lat) && !isNaN(m.lng));

                // Centraliza no primeiro profissional ou, se não houver nenhum, em São Paulo
                const centro = locaisMedicos.length ? [locaisMedicos[0].lat, locaisMedicos[0].lng] : [-23.5505, -46.6333];
                const mapa = L.map('map').setView(centro, 12);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; colaboradores do OpenStreetMap'
                }).addTo(mapa);

                // Um pino para cada profissional; o balão mostra nome, especialidade e endereço
                locaisMedicos.forEach(medico => {
                    const balao = document.createElement('div');
                    const titulo = document.createElement('h3'); titulo.className = 'info-window-title'; titulo.textContent = medico.Nome_Profissional;
                    const esp = document.createElement('p'); esp.className = 'info-window-text'; esp.textContent = 'Especialidade: ' + medico.Especialidade_Profissional;
                    const end = document.createElement('p'); end.className = 'info-window-text'; end.textContent = 'Endereço: ' + (medico.Rua_Profissional || '') + ', ' + (medico.Numero_Profissional || '');
                    balao.append(titulo, esp, end);
                    L.marker([medico.lat, medico.lng], { title: medico.Nome_Profissional }).addTo(mapa).bindPopup(balao);
                });
                if (locaisMedicos.length > 1) mapa.fitBounds(locaisMedicos.map(m => [m.lat, m.lng]), { padding: [40, 40] });
            </script>
        </section>
    </main>

    <footer style="text-align: center; padding: 20px; margin-top: 40px; color: #64748b; font-size: 0.85rem; border-top: 1px solid #e2e8f0;">
        <p>© 2026 Acesso+ | Painel do Beneficiário</p>
    </footer>
</body>
</html>
