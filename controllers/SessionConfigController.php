<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\SessionConfigService;

class SessionConfigController
{
    private SessionConfigService $service;

    public function __construct()
    {
        $this->service = new SessionConfigService();
    }

    private function getJsonInput(): array
    {
        return json_decode(file_get_contents("php://input"), true) ?? [];
    }

    private function jsonResponse(bool $value, string $message = '', $data = null, int $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode([
            'value'   => $value,
            'message' => $message,
            'data'    => $data
        ]);
        exit;
    }

    public function show()
    {
        $r = $this->service->getConfig();
        return $this->jsonResponse($r['value'], $r['message'], $r['data'], $r['status']);
    }

    public function update()
    {
        $data = $this->getJsonInput();
        $r    = $this->service->update($data);
        return $this->jsonResponse($r['value'], $r['message'], $r['data'], $r['status']);
    }
}
