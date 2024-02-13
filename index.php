<?php 

declare(strict_types=1);

function aaaaaaaa(mixed $a = "Estamos aqui com die aaaaaaaa")
{
    var_dump($a);die;
}aaaaaaaa();

use PgSql\Connection;

define("TIME_STAMP", "Y-m-d\TH:i:s.u\Z");

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

    $quando = new DateTime('now');
    $quando = $quando->format(TIME_STAMP);

    if ($tipo === "c") {
        pg_query(conn(), "INSERT INTO transacao (cliente_id, valor, tipo, descricao, quando) 
                            VALUES ($id, $valor, '{$tipo}', '{$descricao}', '{$quando}');
                          UPDATE clientes SET valor = valor + $valor WHERE id = $id;");
    }

    if ($tipo === "d") {
        $query = 
        "DO $$
        BEGIN
            IF (((SELECT valor FROM clientes WHERE id = $id) - $valor) >= ((SELECT limite FROM clientes WHERE id = $id) * -1)) THEN
                INSERT INTO transacao (cliente_id, valor, tipo, descricao, quando) 
                VALUES ($id, $valor, '{$tipo}', '{$descricao}', '{$quando}');
                
                UPDATE clientes 
                SET valor = valor - $valor
                WHERE id = $id; 
            ELSE
                RAISE EXCEPTION 'rinha456limite-excedido';
            END IF;
        END $$;
        SELECT saldoLimite($id)";

        $sql = pg_query(conn(), $query);
        $error = pg_last_error(conn());
        
        if (preg_match("/rinha456limite-excedido/", $error)) {
            http_response_code(422);
            echo json_encode(["message" => "saldo ficara inconsistente, abortado!"]);
            exit;
        }

        $result = pg_fetch_assoc($sql);
    }

    http_response_code(200);
    $array = explode(",", preg_replace(['/\(/', '/\)/'], "", $result["saldolimite"]));
    $limite = (int) $array[1];
    $saldo = (int) $array[2];
    echo json_encode([
        "limite" => $limite,
        "saldo" => $saldo
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === "GET" && $url[3] === "extrato") {
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