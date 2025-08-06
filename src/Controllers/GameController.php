<?php

namespace App\Controllers;

use App\Models\ChessLogic;

class GameController
{
    // 임시 테스트용 메소드
    public function testPieceMoves(): void
    {
        // 백의 룩(e2)이 흑 킹(e8)에 의해 핀(pin)에 걸린 상황
        // 이 룩은 e열을 벗어날 수 없어야 합니다.
        $fen = 'rnbqkbnr/ppp1pppp/8/8/3pP3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1';
        $coord = 'd4'; // e2에 있는 백 룩
        
        $logic = new ChessLogic($fen);
        $validMoves = $logic->getValidMovesForPiece($coord);

        header('Content-Type: application/json');
        echo json_encode([
            'fen' => $fen,
            'piece_at' => $coord,
            'valid_moves_count' => count($validMoves),
            'valid_moves' => $validMoves
        ]);
    }
}