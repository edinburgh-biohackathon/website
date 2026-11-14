<?php
declare(strict_types=1);

// POST /api/challenges — public challenge submission form (challenge/index.html).

const CH_SUBMITTER_TYPES = ['Academic', 'Industry / Company', 'Public Sector', 'SME / Startup', 'Independent Researcher'];
const CH_TRACKS = ['Academic Research', 'Industrial Innovation', 'Non-Coding Problem-Solving'];
const CH_BACKGROUNDS = [
    'Biology / Life Sciences', 'Computational / Data Science', 'Machine Learning / AI',
    'Engineering / Synthetic Biology', 'Design / Communication', 'Business / Entrepreneurship',
    'Drug Design / Medicinal Chemistry',
];
const CH_MENTORING = ['Yes – during challenge introduction + office hours', 'Yes – limited availability', 'No', 'Not sure yet'];
const CH_JUDGING = ['Yes', 'No', 'Maybe'];
const CH_NONCONF = ['Fully non-confidential, shareable, and suitable for student use', 'I’m not sure (we will contact you)'];

function challenge_validate(array $in): array {
    $str = function (string $k, int $max, bool $required) use ($in): string {
        $v = $in[$k] ?? '';
        if (!is_string($v)) fail(422, 'invalid');
        $v = trim($v);
        if (($required && $v === '') || mb_strlen($v) > $max) fail(422, 'invalid');
        return $v;
    };
    $pick = function (string $k, array $allowed, bool $required) use ($in): string {
        $v = $in[$k] ?? '';
        if (!is_string($v)) fail(422, 'invalid');
        if ($v === '' && !$required) return '';
        if (!in_array($v, $allowed, true)) fail(422, 'invalid');
        return $v;
    };

    $d = [
        'name'             => $str('name', 200, true),
        'organisation'     => $str('organisation', 200, true),
        'email'            => $str('email', 254, true),
        'role'             => $str('role', 200, false),
        'submitter_type'   => $pick('submitter_type', CH_SUBMITTER_TYPES, true),
        'challenge_title'  => $str('challenge_title', 160, true),
        'track'            => $pick('track', CH_TRACKS, false),
        'description'      => $str('description', 4000, true),
        'expected_outcome' => $str('expected_outcome', 2000, true),
        'resources'        => $str('resources', 2000, true),
        'mentoring'        => $pick('mentoring', CH_MENTORING, true),
        'judging'          => $pick('judging', CH_JUDGING, true),
        'nonconfidential'  => $pick('nonconfidential', CH_NONCONF, true),
        'notes'            => $str('notes', 1500, false),
    ];
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) fail(422, 'invalid');

    $bg = $in['backgrounds'] ?? [];
    if (!is_array($bg)) fail(422, 'invalid');
    foreach ($bg as $b) if (!is_string($b) || !in_array($b, CH_BACKGROUNDS, true)) fail(422, 'invalid');
    $d['backgrounds'] = array_values(array_unique($bg));
    return $d;
}

function challenge_summary(array $d): string {
    $rows = [
        'Challenge title' => $d['challenge_title'],
        'Track' => $d['track'] ?: '—',
        'Ideal team backgrounds' => $d['backgrounds'] ? implode(', ', $d['backgrounds']) : '—',
        'Name' => $d['name'],
        'Role / title' => $d['role'] ?: '—',
        'Organisation' => $d['organisation'],
        'Submitting as' => $d['submitter_type'],
        'Email' => $d['email'],
        'Mentoring' => $d['mentoring'],
        'Judging' => $d['judging'],
        'Confidentiality' => $d['nonconfidential'],
    ];
    $out = '';
    foreach ($rows as $k => $v) $out .= $k . ': ' . $v . "\n";
    $out .= "\nChallenge description:\n" . $d['description'] . "\n";
    $out .= "\nExpected outcome (2–3 days):\n" . $d['expected_outcome'] . "\n";
    $out .= "\nTools, data, resources provided:\n" . $d['resources'] . "\n";
    if ($d['notes'] !== '') $out .= "\nOther notes:\n" . $d['notes'] . "\n";
    return $out;
}

function challenge_create(): never {
    require_json_same_origin();
    $in = read_json_body();
    $d = challenge_validate($in);
    rate_limit('challenge', 5, 3600);

    $st = db()->prepare('INSERT INTO challenges
        (name, organisation, email, role, submitter_type, challenge_title, track, backgrounds,
         description, expected_outcome, resources, mentoring, judging, nonconfidential, notes, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $st->execute([
        $d['name'], $d['organisation'], $d['email'], $d['role'], $d['submitter_type'], $d['challenge_title'],
        $d['track'], json_encode($d['backgrounds'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $d['description'],
        $d['expected_outcome'], $d['resources'], $d['mentoring'], $d['judging'], $d['nonconfidential'],
        $d['notes'], now_utc(),
    ]);
    $id = (int)db()->lastInsertId();
    $ref = 'C' . $id;
    $c = config();
    $summary = challenge_summary($d);

    send_mail($c['notify_to'], "New challenge $ref: " . $d['challenge_title'],
        "A new challenge was submitted through the website ($ref).\n\n" . $summary .
        "\nReview it in the committee admin: " . $c['site_origin'] . "/admin/\n",
        $d['email']);

    send_mail($d['email'], 'We received your challenge: ' . $d['challenge_title'],
        'Dear ' . $d['name'] . ",\n\n" .
        "Thank you for submitting a challenge to Edinburgh BioHackathon. A copy of your answers is below (reference $ref).\n\n" .
        "What happens next\n" .
        "- The organising committee reviews every challenge for fit with the three tracks. If you weren't sure about confidentiality, we'll contact you first.\n" .
        "- We may contact you to refine the scope, data or expected outcome.\n" .
        "- Selected challenges are announced to participants before the event, with your organisation credited.\n\n" .
        "If anything needs changing, just reply to this email.\n\n" .
        "----\n" . $summary . "----\n\n" .
        "Edinburgh BioHackathon\n" . $c['site_origin'] . "/\n",
        $c['reply_to']);

    json_out(201, ['ok' => true, 'id' => $ref]);
}
