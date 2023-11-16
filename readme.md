# Changelog Behavior simplified(v.1.0.0) 

Simple behavior for your yii2-models 

**forked from [Cranky4/change-log-behavior](https://github.com/Cranky4/change-log-behavior)**

## Installation

1- Install package via composer:
```
composer require antonyz89/change-log-behavior "*"
```
2- Run migrations:
```
yii migrate --migrationPath=@vendor/antonyz89/change-log-behavior/src/migrations
```

## Usage

1- Add *ChangeLogBehavior* to any model or active record:
```php
public function behaviors()
{
    return [
        ...
        [
            'class' => ChangeLogBehavior::className(),
            'excludedAttributes' => ['updated_at'],
        ],
        ...
    ];
}
```
__Attention:__ Behavior watches to "safe" attributes only.
Add attributes into *excludedAttributes* if you don't want to log 
its changes.

2- Add *ChangeLogList* to view:
```php
 echo ChangeLogList::widget([
     'model' => $model,
 ])
```

3- Add custom log:
```php
$model->addCustomLog('hello world!', 'hello_type')
```

### Example

Model *Post*
```php
/**
 * @propertu int id
 * @property int created_at
 * @property int updated_at
 * @property string title
 * @property int rating
 */
class Post extends yii\db\ActiveRecord {
    
    /**
     *  @inheritdoc
     */
    public function behaviors()
    {
        return [
            [
                'class' => ChangeLogBehavior::class,
                'excludedAttributes' => ['created_at','updated_at'],
                // (optional) - custom fields
                'customFields' => [
                    total => static function (self $model) {
                        return $model->calculateTotal();
                    }
                ]
            ]
        ];
    }
}
```

### Custom fields

With custom fields, you can store additional values in a property called `custom_fields`. This feature is useful when you need to save values generated based on other fields or relations.


How it works:

* When finding a model, the custom field is activated to cache the current values of custom fields. Upon saving the model, the custom fields are regenerated to store both the before and after values.
    ```json
    {
        "title": ["Hello World", "New Title"],
        "custom_fields": {
            "total": [50, 100]
        }
    }
    ```

View *post/view.php*
```php
use antonyz89\ChangeLogBehavior\ListWidget as ChangeLogList;
use app\models\Post;

/**
 *  @var Post $model
 */
echo DetailView::widget([
    'model' => $model,
    'attributes' => [
        'id',
        'title',
        'rating',
        'created_at:datetime',
        'updated_at:datetime',
    ],
]);

echo ChangeLogList::widget([
    'model' => $model,
]);

```
