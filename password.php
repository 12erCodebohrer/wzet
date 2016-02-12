<?php

require_once ("inc/config.php");
require_once ("inc/functions.php");

if (!isset ("mode")) {
    die "Error: No password change mode set!";
}

$mode = $_GET['mode'];

if ($mode == "forgot") {
    print <<<EOP
Bitte gib deine E-Mail Adresse ein. Falls die Adresse im System hinterlegt ist, bekommst du einen Link zugesendet, um dein Passwort zur&uuml;ck zu setzen:<br>
<form method="post" action="password.php?mode=mail"><input type="text" name="mail"><br>
<input type="submit" value="Absenden">
</form>
EOP;

    exit ();
}

$db = new SQLite3 ($_CONFIG["DATABASE"]);

if ($mode == "mail") {
    $addr = postOrDie ("mail");
    $random = openssl_random_pseudo_bytes (32);
    $random = base64_encode ($random);
    $q = $db->prepare ("UPDATE Users SET Token = :1 WHERE Mail = :2");
    $q->bindParam (":1", $random);
    $q->bindParam (":2", $addr);
    $q->execute ();

    if ($db->changes () > 0) {
        $link = $_CONFIG["URL"] . "password.php?mode=token&token=$random";
        mail ($addr, "Passwort vergessen", wordwrap ("Hallo, fuer deinen Account bei ".$_CONFIG["URL"]." wurde von der IP ".$_SERVER["REMOTE_ADDR"]." ein neues Passwort angefordert.\n\n<a href="$link">Klicke hier, um ein neues Passwort zu setzen</a>\n\nSollte der Link nicht funktionieren, kopiere ihn in deinen Browser:\n\n$link\n\nSolltest du kein neues Passwort angefordert haben, wende dich bitte an einen Administrator!\n", 70), "From: ". $_CONFIG["MAIL_FROM"]);
    }

    print ("Sollte die angegebene Adresse in der Datenbank hinterlegt sein, so solltest du gleich eine E-Mail mit einem Link f&uuml;r ein neues Passwort bekommen");

    exit ();
}

$token="auth";

if ($mode == "token") {
    $token = getOrDie ("token");
    if (strlen ($token ) < 10) {
        die ("Token is too short!");
    }
    $q = $db->prepare ("SELECT * FROM Users WHERE Token = :1");
    $q->bindParam (":1", $token);
    $r = $q->execute ();
    $_USER = $r->fetchArray ();

    if (!$_USER) {
        die ("Bad Token!");
    }
    $apply .= "&token=$token";
} else if ($mode == "change") {
    include ("inc/core.php");
} else {
    print "Error: Bad mode for password recovery!";
    die ();
}

if (isset ($_POST['pw1']) && isset ($_POST['pw0'])) {
    $pw0 = $_POST['pw0'];
    $pw1 = $_POST['pw1'];
    $ok = 1;
    if ($pw0 != $pw1) {
        print "Die Passw&ouml;rter stimmen nicht &uuml;berein!<br>";
        $ok = 0;
    }

    if (strlen ($pw) < 6) {
        print "Das Passwort ist zu kurz (mindestens 6 Zeichen)!<br>";
        $ok = 0;
    } 
    
    if ($ok != 1) {
        print ("<br>Bitte versuche es noch einmal:<br><br>");
    } else {
        $hash = hashPassword ($pw0);
        $q = $db->prepare ("UPDATE Users SET Password = :1, Token = NULL WHERE ID = :2");
        $q->bindParam (":1", $hash);
        $q->bindParam (":2", $_USERS['ID');
        $q->execute ();
        print ("Das Passwort f&uuml;r ".$_USER['Login']." wurde aktualisiert. <a href='index.php'>Du kannst dich jetzt einloggen</a>.");
        exit ();
    }
}

$u = $_USER["Login"];

print <<<EOP
Zur Erinnerung, dein Loginname ist '$u'.<br>
<form method="post" action="password.php?mode=$mode&token=$token">
Bitte gib dein neues Passwort (mindestens 6 Zeichen) zweimal ein:<br>
<input type="text" name="pw0"><br>
<input type="text" name="pw1"><br>
<input type="submit" value="Absenden">
</form>
EOP;
?>
