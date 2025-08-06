<?php

namespace App\Controllers;

use App\Models\ChessLogic;

class GameController
{
    // 임시 테스트용 메소드
    public function testPieceMoves(): void
    {
        //initial board
        $fen = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
        $coord = 'e2'; // e2에 있는 백 룩
        
        $logic = new ChessLogic($fen);
        $validMoves = $logic->getValidMovesForPiece($coord);
        $newlogic = $logic->move($coord, 'e4'); // 예시로 e2에서 e4로 이동

        header('Content-Type: application/json');
        echo json_encode([
            'fen' => $newlogic->toFen()
        ]);
    }
}