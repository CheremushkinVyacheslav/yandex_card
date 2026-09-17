<?php

namespace App\DTOs;

readonly class ReviewData
{
    public function __construct(
        public string $author,
        public ?string $authorLevel,
        public int $rating,
        public string $text,
        public ?\DateTime $reviewDate,
        public ?string $businessReply,
        public ?\DateTime $businessReplyDate,
        public int $likes,
        public int $dislikes,
        public string $externalId,
        public ?string $avatarUrl = null,
    ) {}
}
