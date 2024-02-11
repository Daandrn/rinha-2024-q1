<?php 

declare(strict_types=1);
define("TIME_STAMP", "Y-m-d\TH:i:s.u\Z");

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $url = explode("/", $_SERVER["REQUEST_URI"]);
    
    $conn = pg_connect("host=localhost port=5432 dbname=transacoes user=postgres password=Danillo@126");
    $id = (int) $url[2];

    $sql = pg_query($conn, "SELECT * FROM clientes WHERE id = $id");
    
    if (pg_num_rows($sql) === 0) {
        http_response_code(404);
        
        echo json_encode(["message" => "id nao encontrado."]);
        exit;
    }
    
    $valor = (int) $_POST["valor"];
    $tipo = $_POST["tipo"];
    $descricao = $_POST["descricao"];

    if (((strlen($_POST["descricao"]) < 1 || strlen($_POST["descricao"]) > 10) || ($tipo !== "d" && $tipo !== "c"))) {
        http_response_code(400);
        echo json_encode(["message" => "descricao ou tipo de transacao invalidos"]);
        exit;
    }
    
    $result = pg_fetch_assoc($sql);
    
    $limite = (int) $result["limite"];
    $saldo = (int) $result["valor"];
    $quando = new DateTime('now');
    $quando = $quando->format(TIME_STAMP);

    if ($tipo === "c") {
        $novoSaldo = (int) $saldo + $valor;
    }

    if ($tipo === "d") {
        $novoSaldo = (int) $saldo - $valor;
        if ($novoSaldo < ($limite * -1)) {
            http_response_code(422);
            echo json_encode(["message" => "saldo ficara inconsistente, abortado!"]);
            exit;
        }
    }
    
    http_response_code(200);

    pg_query($conn, "INSERT INTO transacao (cliente_id, valor, tipo, descricao, quando) 
                        VALUES ($id, $valor, '{$tipo}', '{$descricao}', '{$quando}');
                     UPDATE clientes SET valor = $novoSaldo WHERE id = $id;");

    echo json_encode([
        "limite" => $limite,
        "saldo" => $novoSaldo
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === "GET") {
    $url = explode("/", $_SERVER["REQUEST_URI"]);

    $conn = pg_connect("host=localhost port=5432 dbname=transacoes user=postgres password=Danillo@126");
    $id = (int) $url[2];

    $sql = pg_query($conn, "SELECT * FROM clientes WHERE id = $id");
    
    if (pg_num_rows($sql) === 0) {
        http_response_code(404);
        
        echo json_encode(["message" => "id nao encontrado."]);
        exit;
    }

    $sql = pg_query($conn, "SELECT valor, limite FROM clientes WHERE id = $id;");
    $sql2 = pg_query($conn, "SELECT valor, tipo, descricao, quando AS realizada_em FROM transacao WHERE cliente_id = $id ORDER BY quando DESC LIMIT 10;");
    $date = new DateTime('now');
    $result = pg_fetch_assoc($sql);
    $result2 = pg_fetch_all($sql2);

    http_response_code(200);
    
    echo json_encode([
        "saldo" => [
            "total" => (int) $result["valor"], 
            "data_extrato" => $date->format(TIME_STAMP), 
            "limite" => (int) $result["limite"]
        ],
        "ultimas_transacoes" => [
            $result2
        ]
    ]);
    exit;
}