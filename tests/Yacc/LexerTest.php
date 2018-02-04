<?php
declare(strict_types=1);

namespace PhpYacc\Yacc;

use PHPUnit\Framework\TestCase;

class LexerTest extends Testcase
{
    public static function provideTestAtoms()
    {
        return [
            ["   \t", Token::T_SPACE],
            ["\n", Token::T_NEWLINE],
            ["/* Fooo*/", Token::T_COMMENT],
            ["// Foo", Token::T_COMMENT],
            ["%%", Token::T_MARK],
            ["%token", Token::T_TOKEN],
            ["'f'", "'"],
        ];
    }
    
    /**
     * @dataProvider provideTestAtoms
     */
    public function testAtoms(string $source, $expected)
    {
        $lexer = $this->boot($source);
        $token = $lexer->rawGet();
        $this->assertEquals($expected, $token->t);
        $this->assertEquals($source, $token->v);
    }


    protected function boot(string $source): Lexer
    {
        $lexer = new Lexer();
        $lexer->startLexing($source, "xxx");
        return $lexer;
    }
}
