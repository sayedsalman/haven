<?php
header('Content-Type:text/html;charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<body>

<?php

$emojis = ['❤️','🤗','💪','🫂','🌸','😊','🥺','⭐'];

foreach($emojis as $e){
    echo $e." ";
}

?>

</body>
</html>