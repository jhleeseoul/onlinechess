<?php

namespace App\Controllers;

use App\Models\Shop;
use App\Utils\Auth;

class ShopController
{
    /**
     * 상점의 모든 아이템을 반환
     * 인증된 유저의 경우, 해당 유저가 보유한 아이템 정보도 함께 반환
     * @return void
     */
    public function listItems(): void
    {
        // 토큰이 있으면 유저 ID, 없으면 null
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
            http_response_code(400); // Bad Request
            if (str_contains($result['message'], '보유')) {
                http_response_code(409); // Conflict
            }
            echo json_encode(['message' => $result['message']]);
        }
    }

    /**
     * 사용자의 아이템 인벤토리를 반환
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