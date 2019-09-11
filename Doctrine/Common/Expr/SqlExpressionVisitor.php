<?php

namespace ITE\DoctrineExtraBundle\Doctrine\Common\Expr;

use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Expr\CompositeExpression;
use Doctrine\Common\Collections\Expr\ExpressionVisitor;
use Doctrine\Common\Collections\Expr\Value;
use Doctrine\ORM\Query\Parameter;

/**
 * Class SqlExpressionVisitor
 *
 * @author c1tru55 <mr.c1tru55@gmail.com>
 */
class SqlExpressionVisitor extends ExpressionVisitor
{
    /**
     * @var array|Parameter[]
     */
    private $parameters;

    /**
     * @inheritdoc
     */
    public function walkComparison(Comparison $comparison)
    {
        $parameterName = uniqid(strtolower(preg_replace('/([\W]+)/i', '_', $comparison->getField())));
        $placeholder = ':' . $parameterName;

        $value = $this->walkValue($comparison->getValue());
        $this->parameters[] = new Parameter($parameterName, $value);

        switch ($comparison->getOperator()) {
            case Comparison::EQ;
            case Comparison::NEQ;
            case Comparison::IS;
                if ($value === null) {
                    array_pop($this->parameters);

                    return sprintf(
                        '%s %s %s',
                        $comparison->getField(),
                        $comparison->getOperator() === Comparison::NEQ ? 'IS NOT' : 'IS',
                        'NULL'
                    );
                }

                return sprintf('%s %s %s', $comparison->getField(), $comparison->getOperator(), $placeholder);
            case Comparison::GT;
            case Comparison::GTE;
            case Comparison::LT;
            case Comparison::LTE;
                return sprintf('%s %s %s', $comparison->getField(), $comparison->getOperator(), $placeholder);
            case Comparison::IN;
                return sprintf('%s %s (%s)', $comparison->getField(), Comparison::IN, $placeholder);
            case Comparison::NIN;
                return sprintf('%s %s (%s)', $comparison->getField(), 'NOT IN', $placeholder);
            case Comparison::CONTAINS;
                $value = '%' . $this->walkValue($comparison->getValue()) . '%';
                $this->parameters[count($this->parameters) - 1] = new Parameter($parameterName, $value);

                return sprintf('%s %s %s', $comparison->getField(), 'LIKE', $placeholder);
            default:
                return sprintf('%s %s %s', $comparison->getField(), $comparison->getOperator(), $placeholder);
        }
    }

    /**
     * @inheritdoc
     */
    public function walkValue(Value $value)
    {
        return $value->getValue();
    }

    /**
     * @inheritdoc
     */
    public function walkCompositeExpression(CompositeExpression $expr)
    {
        $expressionList = [];
        foreach ($expr->getExpressionList() as $child) {
            $expressionList[] = $this->dispatch($child);
        }

        switch($expr->getType()) {
            case CompositeExpression::TYPE_AND:
                return '(' . implode(' AND ', $expressionList) . ')';
            case CompositeExpression::TYPE_OR:
                return '(' . implode(' OR ', $expressionList) . ')';
            default:
                throw new \RuntimeException("Unknown composite " . $expr->getType());
        }
    }

    /**
     * @return array|Parameter[]
     */
    public function getParameters()
    {
        return $this->parameters;
    }
}
