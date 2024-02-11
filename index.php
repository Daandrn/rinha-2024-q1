<?php 

declare(strict_types=1);

use PgSql\Connection;

$url = explode("/", $_SERVER["REQUEST_URI"]);

if ($url[1] === "clientes" 
    && is_numeric($url[2]) 
    && in_array($url[2], [1, 2, 3, 4, 5])
    ) {
        $id = (int) $url[2];
} else {
    http_response_code(404);
    exit;
}

define("TIME_STAMP", "Y-m-d\TH:i:s.u\Z");

function conn(): Connection|false
{
    return $conn = pg_connect("host=db port=5432 dbname=rinha user=rinha password=456");
}

if ($_SERVER['REQUEST_METHOD'] === "POST" && $url[3] === "transacoes") {
    $requestJson = file_get_contents('php://input');
    $request = json_decode($requestJson, true);
    
    if ((!is_int($request["valor"])) 
        || ($request["tipo"] !== "d" && $request["tipo"] !== "c") 
        || (!is_string($request["descricao"]) || (strlen($request["descricao"]) < 1 || strlen($request["descricao"]) > 10))
    ) {
        http_response_code(422);
        echo json_encode(["message" => "body da requisicao invalido"]);
        exit;
    }
    
    $valor = $request["valor"];
    $tipo = $request["tipo"];
    $descricao = $request["descricao"];
    
    $sql = pg_query(conn(), "SELECT * FROM clientes WHERE id = $id");
    
    if (pg_num_rows($sql) === 0) {
        http_response_code(404);
        echo json_encode(["message" => "id nao encontrado."]);
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
    
    pg_query(conn(), "INSERT INTO transacao (cliente_id, valor, tipo, descricao, quando) 
                        VALUES ($id, $valor, '{$tipo}', '{$descricao}', '{$quando}');
                     UPDATE clientes SET valor = $novoSaldo WHERE id = $id;");

    http_response_code(200);

    echo json_encode([
        "limite" => $limite,
        "saldo" => $novoSaldo
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === "GET" && $url[3] === "extrato") {

    $sql = pg_query(conn(), "SELECT * FROM clientes WHERE id = $id");
    
    if (pg_num_rows($sql) === 0) {
        http_response_code(404);
        echo json_encode(["message" => "id nao encontrado."]);
        exit;
    }

    $sql = pg_query(conn(), "SELECT valor, limite FROM clientes WHERE id = $id;");
    $sql2 = pg_query(conn(), "SELECT valor, tipo, descricao, quando AS realizada_em FROM transacao WHERE cliente_id = $id ORDER BY quando DESC LIMIT 10;");
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

http_response_code(404);
exit;