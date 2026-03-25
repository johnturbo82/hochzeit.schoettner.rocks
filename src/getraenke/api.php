<?php
header('Content-Type: application/json; charset=utf-8');

$dataFile = __DIR__ . '/../data/getraenke.json';

function loadData(string $file): array {
    if (!file_exists($file)) return [];
    $json = file_get_contents($file);
    return json_decode($json, true) ?? [];
}

function saveData(string $file, array $data): void {
    usort($data, fn($a, $b) => $b['votes'] - $a['votes']);
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    echo json_encode(loadData($dataFile), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Eingabe']);
        exit;
    }

    $action = $input['action'] ?? '';

    if ($action === 'add') {
        $name = trim($input['name'] ?? '');
        if ($name === '' || mb_strlen($name) > 100) {
            http_response_code(400);
            echo json_encode(['error' => 'Ungültiger Name (max. 100 Zeichen)']);
            exit;
        }

        $data = loadData($dataFile);

        // Duplikat-Prüfung (case-insensitive)
        foreach ($data as &$item) {
            if (mb_strtolower($item['name']) === mb_strtolower($name)) {
                $item['votes']++;
                saveData($dataFile, $data);
                echo json_encode(['status' => 'exists', 'id' => $item['id']]);
                exit;
            }
        }
        unset($item);

        $newItem = [
            'id'    => bin2hex(random_bytes(8)),
            'name'  => $name,
            'votes' => 1,
        ];
        $data[] = $newItem;
        saveData($dataFile, $data);
        echo json_encode(['status' => 'added', 'id' => $newItem['id']]);
        exit;
    }

    if ($action === 'vote') {
        $id = $input['id'] ?? '';
        if (!preg_match('/^[0-9a-f]{16}$/', $id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Ungültige ID']);
            exit;
        }

        $data = loadData($dataFile);
        $found = false;
        foreach ($data as &$item) {
            if ($item['id'] === $id) {
                $item['votes']++;
                $found = true;
                break;
            }
        }
        unset($item);

        if (!$found) {
            http_response_code(404);
            echo json_encode(['error' => 'Getränk nicht gefunden']);
            exit;
        }

        saveData($dataFile, $data);
        echo json_encode(['status' => 'voted']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Unbekannte Aktion']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Methode nicht erlaubt']);
