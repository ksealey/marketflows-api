<?php 
namespace App\Traits;

trait AppliesConditions
{
    public function applyConditions($query, array $conditionGroups)
    {
        $query->where(function($query) use($conditionGroups){
            foreach( $conditionGroups as $conditionGroupIndex => $conditionGroup ){
                if( $conditionGroupIndex === 0 ){
                    $query->where(function($query) use($conditionGroup){
                        $this->conditionQuery($query, $conditionGroup);

                    });
                }else{
                    $query->orWhere(function($query) use($conditionGroup){
                        $this->conditionQuery($query, $conditionGroup);
                    });
                }
            }
        });
        

        return $query;
    }

    public function conditionQuery($query, $conditionGroup)
    {
        foreach( $conditionGroup as $condition ){
            if( is_array($condition) )
                $condition = (object)$condition;

            $hasValue = false;
            if( !empty($condition->inputs) ){
                foreach($condition->inputs as $input){
                    if( isset($input) && $input !== '' ) 
                        $hasValue = true;
                }
            }

            if( $condition->operator === 'EQUALS' ){
                if( isset($condition->inputs[0]) && $condition->inputs[0] !== '' )
                    $query->where($condition->field, '=', $condition->inputs[0]);
            }elseif( $condition->operator === 'NOT_EQUALS' ){
                if( isset($condition->inputs[0]) && $condition->inputs[0] !== '' )
                    $query->where($condition->field, '!=', $condition->inputs[0]);
            }elseif( $condition->operator === 'IN' ){
                if( $hasValue )
                    $query->whereIn($condition->field, $condition->inputs);
            }elseif( $condition->operator === 'NOT_IN' ){
                if( $hasValue )
                    $query->whereNotIn($condition->field, $condition->inputs);
            }elseif( $condition->operator === 'EMPTY' ){
                $query->where(function($query) use($condition){
                    $query->whereNull($condition->field)
                          ->orWhere($condition->field, '=', '');
                });
            }elseif( $condition->operator === 'NOT_EMPTY' ){
                $query->where(function($query) use($condition){
                    $query->whereNotNull($condition->field)
                            ->orWhere($condition->field, '!=', '');
                });
            }elseif( $condition->operator === 'IS_TRUE' ){
                $query->where($condition->field, 1);
            }elseif( $condition->operator === 'IS_FALSE' ){
                $query->where($condition->field, 0);
            }elseif( $condition->operator === 'LIKE' ){
                if( isset($condition->inputs[0]) && $condition->inputs[0] !== '' )
                    $query->where($condition->field, 'like', '%' . $condition->inputs[0] . '%');
            }elseif( $condition->operator === 'NOT_LIKE' ){
                if( isset($condition->inputs[0]) && $condition->inputs[0] !== '' )
                    $query->where($condition->field, 'not like', '%' . $condition->inputs[0] . '%');
            }
        }

        return $query;
    }
}