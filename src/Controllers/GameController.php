<?php

namespace App\Controllers;

use App\Models\ChessLogic;

class GameController
{
    // 임시 테스트용 메소드
    public function testPieceMoves(): void
    {
        // 백의 폰 e2가 움직일 수 있는 위치 테스트
        $fen = 'rnbqkbnr/ppp1pppp/8/3p4/4P3/8/PPPP1PPP/RNBQKBNR w KQkq - 0 1';
        $coord = 'a7';
        
        $logic = new ChessLogic($fen);
        $validMoves = $logic->getValidMovesForPiece($coord);

        header('Content-Type: application/json');
        echo json_encode([
            'fen' => $fen,
            'piece_at' => $coord,
            'valid_moves' => $validMoves
        ]);
    }
}