<?php
declare(strict_types=1);

// Session locking serializes repeated POSTs. Existing DB transactions still own stock integrity.
function cssrSubmissionReplay(string $scope, array $payload): ?array
{
    $key = $_POST['submission_key'] ?? '';
    if (!is_string($key) || !preg_match('/^[a-f0-9]{32}$/D', $key)) throw new DomainException('Refresh this form before saving. Your security token has expired.');
    $entry = $_SESSION['cssr_submissions'][$scope.':'.$key] ?? null;
    if (!$entry) return null;
    if (!hash_equals($entry['hash'], hash('sha256', serialize($payload)))) throw new DomainException('This form has already been saved. Refresh it before adding another item.');
    return $entry['result'];
}

function cssrRememberSubmission(string $scope, array $payload, array $result): void
{
    $_SESSION['cssr_submissions'][$scope.':'.$_POST['submission_key']] = ['hash'=>hash('sha256',serialize($payload)), 'result'=>$result];
    if (count($_SESSION['cssr_submissions']) > 100) $_SESSION['cssr_submissions'] = array_slice($_SESSION['cssr_submissions'], -100, null, true);
}

function cssrSubmissionField(): string
{
    return '<input type="hidden" name="submission_key" value="'.bin2hex(random_bytes(16)).'">';
}
