<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>time</title>
</head>

<body>
    <h1>String & String Operator</h1>
    <?php
    echo "Hello \"w\"ord";
    ?>
    <h2>concatenation operator</h2>
    <?php
    echo "Hello " . "world";
    ?>
    <h2>String length function</h2>
    
    <?php
    echo strlen("Hello world");
    ?>
    
    <br><br>
    <?php
    $name = "king";
    echo "my name is " . $name . ".";
    ?>
    <br><br>
    <?php
    echo date('Y-m-d H:i:s');
    echo '<br>';
    echo 'PHP version: ' . phpversion();
    echo '<br>';
    echo 'Server software: ' . $_SERVER['SERVER_SOFTWARE'];
    echo '<br>';
    echo 'Document root: ' . $_SERVER['DOCUMENT_ROOT'];
    echo '<br>';
    echo 'Server protocol: ' . $_SERVER['SERVER_PROTOCOL'];
    ?>

    <?php
    $redis = new Redis(); // Redis 클래스의 인스턴스를 생성
    $redis->connect('127.0.0.1', 6379); // 로컬 Redis 서버에 연결 (기본 포트 6379)
    $redis->set("test_key", "Hello Redis!"); // "test_key"라는 키에 "Hello Redis!"라는 값을 저장
    echo $redis->get("test_key"); // 저장한 값을 가져와서 출력
    ?>


</body>

</html>