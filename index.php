<?php 

declare(strict_types=1);

function aaaaaaaa(mixed $a = "Estamos aqui com die aaaaaaaa")
{
    var_dump($a);die;
}

use PgSql\Connection;

define("TIME_STAMP", "Y-m-d\TH:i:s.u\Z");

$url = explode("/", $_SERVER["REQUEST_URI"]);

if ($url[1] === "clientes" 
    && is_numeric($url[2]) 
    ) {
        $id = (int) $url[2];
} else {
    http_response_code(404);
    exit;
}



if ($_SERVER['REQUEST_METHOD'] === "POST" && $url[3] === "transacoes") {
    $requestJson = file_get_contents('php://input');
    $request = json_decode($requestJson, true);
    
    $conn = pg_connect("host=db port=5432 dbname=rinha user=rinha password=456");

    if ((!is_int($request["valor"])) 
        || ($request["tipo"] !== "d" && $request["tipo"] !== "c") 
        || (!is_string($request["descricao"]) 
            || (strlen($request["descricao"]) < 1 
            || strlen($request["descricao"]) > 10))
    ) {
        http_response_code(422);
        echo json_encode(["message" => "body da requisicao invalido"]);
        exit;
    }
    
    $valor = $request["valor"];
    $tipo = $request["tipo"];
    $descricao = $request["descricao"];

    $quando = new DateTime('now');
    $quando = $quando->format(TIME_STAMP);

    pg_query($conn, "BEGIN");

    $result = pg_query($conn, "SELECT limite, valor AS saldo FROM clientes WHERE id = $id FOR UPDATE;");

    if (pg_num_rows($result) < 1) {
        pg_query($conn, "ROLLBACK;");
        http_response_code(404);
        echo json_encode(["message" => "id nao existe!"]);
        exit;
    }
    
    $client = pg_fetch_object($result);

    if ($tipo === "c") {
        $novoSaldo = (int) $client->saldo + $valor;
    }

    if ($tipo === "d") {
        $novoSaldo = (int) $client->saldo - $valor;
    }

    if ($novoSaldo < -$client->limite) {
        pg_query($conn, "ROLLBACK;");
        http_response_code(422);
        echo json_encode(["message" => "saldo ficara inconsistente, abortado!"]);
        exit;
    }
    
    $query = 
    "INSERT INTO transacao (cliente_id, valor, tipo, descricao, quando) 
    VALUES ($id, $novoSaldo, '{$tipo}', '{$descricao}', '{$quando}');
    
    UPDATE clientes 
    SET valor = $novoSaldo
    WHERE id = $id";

    pg_query($conn, $query);
    pg_query($conn, "COMMIT;");
    
    http_response_code(200);
    echo json_encode([
        "limite" => $client->limite,
        "saldo" => $novoSaldo
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === "GET" && $url[3] === "extrato") {
    $conn = pg_connect("host=db port=5432 dbname=rinha user=rinha password=456");
    pg_query($conn, "BEGIN;");
    $sql = pg_query($conn, "SELECT valor, limite FROM clientes WHERE id = $id LIMIT 1;");

    if (pg_num_rows($sql) < 1) {
        pg_query($conn, "ROLLBACK;");
        http_response_code(404);
        echo json_encode(["message" => "id nao existe!"]);
        exit;
    }

    $sql2 = pg_query($conn, "SELECT valor, tipo, descricao, quando AS realizada_em FROM transacao WHERE cliente_id = $id ORDER BY quando DESC LIMIT 10;");
    
    
    pg_query($conn, "COMMIT;");

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