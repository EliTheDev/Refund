<?php

namespace lib\commandx;

interface DynamicCompletionProvider extends CompletionProvider
{
    public function getEnumName(): string;
}
