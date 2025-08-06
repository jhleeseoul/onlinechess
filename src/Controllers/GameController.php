<?php

namespace App\Controllers;

use App\Models\ChessLogic;

class GameController
{
    // 임시 테스트용 메소드
    public function testPieceMoves(): void
    {
        //initial board
        $fen = '5k2/8/5R2/8/8/8/8/7K b - - 0 1';
        $coord = 'f8'; // e2에 있는 백 룩
        
        $logic = new ChessLogic($fen);
        $validMoves = $logic->getValidMovesForPiece($coord);

        header('Content-Type: application/json');
        echo json_encode([
            'fen' => $logic->toFen(),
            'piece_at' => $coord,
            'valid_moves_count' => count($validMoves),
            'valid_moves' => $validMoves,
            'is_checkmate' => $logic->isCheckmate(),
            'is_stalemate' => $logic->isStalemate(),
            'is_check' => $logic->isCheck(),
        ]);
    }
}