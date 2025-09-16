<?php

namespace App\Controllers;

use App\Models\Shop;
use App\Utils\Auth;

class ShopController
{
    public function listItems(): void
    {
        // 토큰이 있으면 유저 ID를, 없으면 null을 가져옴
        $authedUser = \App\Utils\Auth::getAuthUser();
        $userId = $authedUser ? $authedUser->userId : null;

        $shopModel = new Shop();
        $items = $shopModel->getAllItems($userId);
        
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

    /**
     * 사용자의 아이템 인벤토리를 반환합니다.
     * @return void
     */
    public function getMyInventory(): void
    {
        $authedUser = Auth::getAuthUser();
        if (!$authedUser) {
            http_response_code(401);
            echo json_encode(['message' => 'Authentication required.']);
            return;
        }

        $shopModel = new Shop();
        $inventory = $shopModel->getUserInventory($authedUser->userId);
        
        http_response_code(200);
        echo json_encode($inventory, JSON_UNESCAPED_UNICODE);
    }
}