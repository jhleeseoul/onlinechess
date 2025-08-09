<?php

namespace App\Controllers;

use App\Models\Shop;
use App\Utils\Auth;

class ShopController
{
    public function listItems(): void
    {
        $shopModel = new Shop();
        $items = $shopModel->getAllItems();
        
        http_response_code(200);
        echo json_encode($items);
    }

    public function buyItem(int $itemId): void
    {
        $authedUser = Auth::getAuthUser();
        if (!$authedUser) {
            http_response_code(401);
            echo json_encode(['message' => 'Authentication required.']);
            return;
        }

        $shopModel = new Shop();
        $result = $shopModel->purchaseItem($authedUser->userId, $itemId);

        if ($result['status'] === 'success') {
            http_response_code(200);
            echo json_encode(['message' => $result['message']]);
        } else {
            // 모델에서 보내준 에러 메시지에 따라 적절한 상태 코드 반환
            http_response_code(400); // Bad Request (일반적인 실패)
            if (str_contains($result['message'], '보유')) {
                http_response_code(409); // Conflict (중복)
            }
            echo json_encode(['message' => $result['message']]);
        }
    }
}