<?php

namespace App\Models;

class ChessLogic
{
    /** @var array 8x8 체스 보드, FEN을 파싱한 결과가 저장됨 */
    private array $board;

    /** @var string 'w' 또는 'b' */
    private string $currentTurn;

    // ... (캐슬링, 앙파상 등 다른 FEN 정보 프로퍼티들) ...

    public function __construct(string $fen)
    {
        $this->parseFen($fen);
    }

    /**
     * FEN 문자열을 파싱하여 클래스 프로퍼티를 초기화합니다.
     * 즉, 이차원 배열 형태의 board 상태를 생성합니다.
     * @param string $fen
     */
    private function parseFen(string $fen): void
    {
        $parts = explode(' ', $fen);
        $piecePlacement = $parts[0];
        $this->currentTurn = $parts[1];
        // ... (나머지 부분 파싱 로직은 나중에 추가)

        // 보드 상태 파싱
        $this->board = [];
        $rows = explode('/', $piecePlacement);
        for ($r = 0; $r < 8; $r++) {
            $this->board[$r] = [];
            $col = 0;
            foreach (str_split($rows[$r]) as $char) {
                if (is_numeric($char)) {
                    for ($i = 0; $i < (int)$char; $i++) {
                        $this->board[$r][$col] = null; // 빈 칸
                        $col++;
                    }
                } else {
                    $this->board[$r][$col] = $char; // 말
                    $col++;
                }
            }
        }
    }

    // 테스트용: 현재 보드 상태를 보기 쉽게 출력하는 메소드
    public function getBoard(): array
    {
        return $this->board;
    }
}