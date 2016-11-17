<?php

namespace GrumPHP\Parser\Php\Visitor;

use GrumPHP\Parser\ParseError;
use PhpParser\Node;

/**
 * Class NeverUseElseVisitor
 *
 * @package GrumPHP\Parser\Php\Visitor
 */
class NeverUseElseVisitor extends AbstractVisitor
{
    /**
     * @link http://www.slideshare.net/rdohms/your-code-sucks-lets-fix-it-15471808
     * @link http://www.slideshare.net/guilhermeblanco/object-calisthenics-applied-to-php
     *
     * @param Node $node
     *
     * @return void
     */
    public function leaveNode(Node $node)
    {
        if (!$node instanceof Node\Stmt\Else_ && !$node instanceof Node\Stmt\ElseIf_) {
            return;
        }

        $this->addError(
            sprintf(
                'Object Calisthenics error: Do not use the "%s" keyword!',
                $node instanceof  Node\Stmt\ElseIf_ ? 'elseif' : 'else'
            ),
            $node->getLine(),
            ParseError::TYPE_ERROR
        );
    }
}
