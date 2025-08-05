<?php

namespace App\Controllers;

use App\Models\ChessLogic;

class GameController
{
    // 임시 테스트용 메소드
    public function testPieceMoves(): void
    {
        // 중앙에 백 퀸(Q)을 놓고 테스트
        $fen = '8/8/8/4k3/8/8/8/8 w - - 0 1';
        $coord = 'e5'; // e4에 있는 퀸의 움직임
        
        $logic = new ChessLogic($fen);
        $validMoves = $logic->getValidMovesForPiece($coord);

        header('Content-Type: application/json');
        echo json_encode([
            'fen' => $fen,
            'piece_at' => $coord,
            'valid_moves_count' => count($validMoves), // 결과가 너무 길 수 있으니 개수만 확인
            'valid_moves' => $validMoves
        ]);
    }
}