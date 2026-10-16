<?php

return [
    'max_per_document' => (int) env('PROOFS_MAX_PER_DOCUMENT', 5),
    'max_bytes_per_user' => (int) env('PROOFS_MAX_BYTES_PER_USER', 250 * 1024 * 1024),
    'max_bytes_total' => (int) env('PROOFS_MAX_BYTES_TOTAL', 2 * 1024 * 1024 * 1024),
];
