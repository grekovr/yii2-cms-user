<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model app\models\Tour */
/* @var $form yii\widgets\ActiveForm */
?>
<?php $language = 'nl'; ?>
<div class="tour-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= Html::activeHiddenInput($model->{$language}, 'tour_id');?>
    <?= Html::activeHiddenInput($model->{$language}, 'language');?>

    <?php echo $form->field($model->{$model->language}, 'title')->textInput(); ?>
    <?php echo $form->field($model->{$model->language}, 'description')->textInput(); ?>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? Yii::t('app', 'Create') : Yii::t('app', 'Update'), ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>


    <?php ActiveForm::end(); ?>

</div>
