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

### Custom fields

With custom fields, you can store additional values in a property called `custom_fields`. This feature is useful when you need to save values generated based on other fields or relations.

```php
public function behaviors()
{
    return [
        [
            'class' => ChangeLogBehavior::class,
            'autoCache' => true,
            'customFields' => [
                'total' => static function (self $model) {
                    return $model->calculateTotal();
                },
                // or static function (self $model) { return $model->createdBy->name; }
                'created_by' => 'createdBy.name'
            ]
        ]
    ];
}
```

Use `!` after the field's name to force it to be saved even if it hasn't changed.

```php
public function behaviors()
{
    return [
        [
            'class' => ChangeLogBehavior::class,
            'autoCache' => true,
            'customFields' => [
                'total' => static function (self $model) {
                    return $model->calculateTotal();
                },
                // `user_id` will be registered even if it hasn't changed
                'user_id!' => 'user.name',
                'created_by' => 'createdBy.name'
            ]
        ]
    ];
}
```

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

* Auto cache custom fields
    - By default `$autoCache` is `false` and the custom fields are not cached on trigger `ActiveRecord::EVENT_AFTER_FIND` to prevent performance issues.
    - Call `cacheCustomFields()` to cache the custom fields manually.
        ```php
        class FooController extends Controller {
            // ...

            public function actionUpdate($id) {
                $model = $this->findModel($id);
                // cache custom fields manually
                // [[cacheCustomFields()]] is a magic method that calls [[ChangeLogBehavior::cacheCustomFields()]]
                $model->cacheCustomFields();

                $modelChildren = array_map(function () {
                    // imagine something cool here
                }, $this->request->post());

                foreach ($modelChildren as $modelChild) {
                    $modelChild->parent_id = $model->id;
                    $modelChild->save();
                }

                // on save the custom fields are computed again and saved if they changed
                $model->save();
            }
        }
        ```
    - To enable `$autoCache` set it to `true` on behaviors and the custom fields will be cached on trigger `ActiveRecord::EVENT_AFTER_FIND`. But be careful.

### Save data on delete

By default the behavior doesn't save data on delete. Set `dataOnDelete` to `true` to save data on delete.

```php
/**
 *  @inheritdoc
 */
public function behaviors()
{
    return [
        [
            'class' => ChangeLogBehavior::class,
            'dataOnDelete' => true
        ]
    ];
}
```

The result will be something like:


```json
{
    "field_1": ["value", null],
    "field_2": ["value", null],
    "field_3": ["value", null],
}
```

Last value is always `null`.

`dataOndelete = true` also save custom fields.

## Parent Id

Set a parent id to change log.

This is useful to create a custom view for your changelog view.

default: `null`, accept: `null` | `string` | `callable`

```php
public function behaviors()
{
    return [
        [
            'class' => ChangeLogBehavior::class,
            // get `user_id` from model ($model->user_id)
            'parentId' => 'user_id', 
            // get `user_id` from model using static function
            'parentId' => static functiobn (self $model) {
                if ($model->type !== 'ADMIN')
                    return $model->user_id;
                }

                return null;
        ]
    ];
}
```

## Example

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
                // (optional) autoCache is disabled by default
                'autoCache' => false,
                // (optional) - custom fields
                'customFields' => [
                    'total' => static function (self $model) {
                        return $model->calculateTotal();
                    },
                    // or static function (self $model) { return $model->createdBy->name; }
                    'created_by' => 'createdBy.name'
                ]
            ]
        ];
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
