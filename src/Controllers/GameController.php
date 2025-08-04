<?php

namespace App\Controllers;

use App\Models\ChessLogic;

class GameController
{
    // 임시 테스트용 메소드
    public function testFenParser(): void
    {
        // 체스 초기 상태 FEN
        $fen = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
        
        $logic = new ChessLogic($fen);
        $board = $logic->getBoard();

        header('Content-Type: application/json');
        echo json_encode($board);
    }
}