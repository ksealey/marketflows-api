<?php 
namespace App\Traits;

trait AppliesConditions
{
    public function applyConditions($query, array $conditions)
    {
        $query->where(function($query) use($conditions){
            foreach( $conditions as $condition ){
                if( $condition->operator === 'EQUALS' ){
                    if( ! empty($condition->inputs[0]) )
                        $query->where($condition->field, '=', $condition->inputs[0]);
                }elseif( $condition->operator === 'NOT_EQUALS' ){
                    if( ! empty($condition->inputs[0]) )
                        $query->where($condition->field, '!=', $condition->inputs[0]);
                }elseif( $condition->operator === 'IN' ){
                    $hasValue = false;
                    foreach($condition->inputs as $input){
                        if( $input )
                            $hasValue = true;
                    }
                    if( $hasValue )
                        $query->whereIn($condition->field, $condition->inputs);
                }elseif( $condition->operator === 'NOT_IN' ){
                    $hasValue = false;
                    foreach($condition->inputs as $input){
                        if( $input ) 
                            $hasValue = true;
                    }
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
                }
            }
        });
        return $query;
    }
}