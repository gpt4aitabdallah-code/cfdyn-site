<?php
/*
 * CFDYN — réception du formulaire de contact / demande de devis.
 * Envoie la demande à contact@cfdyn.com via la fonction mail() de l'hébergement OVH.
 * Réponse JSON : {"ok":true} ou {"ok":false,"error":"..."}
 */

date_default_timezone_set('Europe/Paris');
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

const DEST = 'contact@cfdyn.com';
const FROM = 'contact@cfdyn.com';   // doit appartenir au domaine hébergé chez OVH

function reply($ok, $code = 200, $error = '') {
    http_response_code($code);
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    reply(false, 405, 'method');
}

// Anti-spam : champ piège invisible (doit rester vide) et délai minimal de saisie
if (!empty($_POST['website'])) {
    reply(true); // on fait comme si tout allait bien pour ne pas renseigner le robot
}
$started = (int)($_POST['t'] ?? 0);
if ($started > 0 && (time() * 1000 - $started) < 3000) {
    reply(true);
}

function field($key, $max) {
    $v = trim((string)($_POST[$key] ?? ''));
    $v = str_replace("\0", '', $v);
    return mb_substr($v, 0, $max, 'UTF-8');
}
function oneline($v) {
    return trim(preg_replace('/[\r\n\t]+/', ' ', $v));
}

$name  = oneline(field('name', 120));
$org   = oneline(field('org', 160));
$email = oneline(field('email', 160));
$tel   = oneline(field('tel', 40));
$type  = oneline(field('type_label', 80));
$phase = oneline(field('phase_label', 80));
$msg   = field('msg', 8000);
$lang  = field('lang', 2) === 'en' ? 'en' : 'fr';

if ($name === '' || $email === '' || $msg === '') {
    reply(false, 400, 'missing');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    reply(false, 400, 'email');
}

$subject = 'Demande de devis' . ($type !== '' ? ' – ' . $type : '') . ' – ' . $name;
$encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

$body  = "Nouvelle demande reçue depuis le formulaire de cfdyn.com\n";
$body .= str_repeat('-', 56) . "\n";
$body .= "Nom        : $name\n";
$body .= "Société    : " . ($org !== '' ? $org : '-') . "\n";
$body .= "Courriel   : $email\n";
$body .= "Téléphone  : " . ($tel !== '' ? $tel : '-') . "\n";
$body .= "Type       : " . ($type !== '' ? $type : '-') . "\n";
$body .= "Phase      : " . ($phase !== '' ? $phase : '-') . "\n";
$body .= "Langue     : " . strtoupper($lang) . "\n";
$body .= str_repeat('-', 56) . "\n\n";
$body .= $msg . "\n\n";
$body .= str_repeat('-', 56) . "\n";
$body .= 'Envoyé le ' . date('d/m/Y à H:i') . ' depuis ' . ($_SERVER['REMOTE_ADDR'] ?? '?') . "\n";
$body .= "Répondre à ce courriel répond directement au demandeur.\n";

$encodedName = '=?UTF-8?B?' . base64_encode('CFDYN – site web') . '?=';
$headers  = "From: $encodedName <" . FROM . ">\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "Content-Transfer-Encoding: 8bit\r\n";
$headers .= "X-Mailer: cfdyn-contact\r\n";

$sent = @mail(DEST, $encodedSubject, $body, $headers, '-f' . FROM);

reply($sent, $sent ? 200 : 500, $sent ? '' : 'send');
