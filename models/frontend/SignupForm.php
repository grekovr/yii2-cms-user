<?php
namespace infoweb\user\models\frontend;

use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\helpers\Html;
use infoweb\user\models\Profile;
use infoweb\email\models\Email;
use infoweb\member\models\Member;
use infoweb\member\models\Rep;

/**
 * Signup form
 */
class SignupForm extends Model
{
    public $username;
    public $email;
    public $password;
    public $password_repeat;
    public $salutation;
    public $firstname;
    public $name;
    public $language;
    public $country;
    public $profession;
    public $workplace_type;
    public $workplace_name;
    public $address;
    public $city;
    public $zipcode;
    public $phone;
    public $mobile;
    public $riziv_number;
    public $doctorcode;
    public $apb_number;
    public $order_of_pharmacists_number;
    public $responsible_pneumologist;
    public $profession_declaration;
    public $ref;
    public $repId;
    
    public $registration_source;

    // Attributes for the mail that is send when the form is submitted
    public $body;
    public $subject;
    public $to;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['salutation', 'name', 'firstname', 'email', 'address', 'zipcode', 'city', 'profession', 'username', 'password', 'profession_declaration', 'language', 'country'], 'required'],
            [['name', 'firstname', 'email', 'address', 'zipcode', 'city', 'phone', 'mobile', 'username', 'workplace_name', 'responsible_pneumologist'], 'trim'],
            ['language', 'string', 'max' => 2],
            ['language', 'default', 'value' => Yii::$app->language],
            // Username has to be unique
            ['username', 'unique', 'targetClass' => 'infoweb\user\models\frontend\User', 'message' => Yii::t('infoweb/user', 'This username has already been taken.')],
            ['username', 'string', 'min' => 3, 'max' => 255],
            ['email', 'email'],
            // Emailaddress has to be unique
            ['email', 'unique', 'targetClass' => 'infoweb\user\models\frontend\User', 'message' => Yii::t('infoweb/user', 'This email address has already been taken.')],
            [['profession_declaration'], 'compare', 'compareValue' => 1],
            // The password must contain at least one number and one symbol
            [['password', 'password_repeat'], 'match', 'pattern' => '/^(?=.*[0-9])(?=.*[!@#$%^&*])[A-Za-z0-9!@#$%^&*]{6,}$/'],
            // Passwords must match
            ['password', 'compare', 'compareAttribute' => 'password_repeat'],
            // Nurses and pneumologists must have a specific workplace_type
            ['workplace_type', 'in', 'range' => [Profile::WORKPLACETYPE_HOSPITAL, Profile::WORKPLACETYPE_PRIVATE], 'when' => function($model) {
                return in_array($model->profession, [Profile::PROFESSION_PNEUMOLOGIST, Profile::PROFESSION_NURSE]);
            }],
            // Nurses and pneumologists must have a workplace_name
            ['workplace_name', 'required', 'when' => function($model) {
                return in_array($model->profession, [Profile::PROFESSION_PNEUMOLOGIST, Profile::PROFESSION_NURSE]);
            }],
            // A nurse must have a responsible pneumologist
            ['responsible_pneumologist', 'required', 'when' => function($model) {
                return in_array($model->profession, [Profile::PROFESSION_NURSE]);
            }],
            // Pharmacists need an APB number
            ['apb_number', 'required', 'when' => function($model) {
                return $model->profession == Profile::PROFESSION_PHARMACIST;
            }],
            // All the rest needs a riziv number
            ['riziv_number', 'required', 'when' => function($model) {
                if($model->country == Profile::COUNTRY_BE) {
                    return !in_array($model->profession, [Profile::PROFESSION_PHARMACIST, Profile::PROFESSION_NURSE, '']);
                }

                return false;
            }],
            ['doctorcode', 'required', 'when' => function($model) {
                if($model->country == Profile::COUNTRY_LU) {
                    return !in_array($model->profession, [Profile::PROFESSION_PHARMACIST, Profile::PROFESSION_NURSE, '']);
                }

                return false;
            }],
            ['riziv_number', 'match', 'pattern' => '/^[0-9]{1}-[0-9]{5}-[0-9]{2}-[0-9]{3}$/'],
            ['apb_number', 'match', 'pattern' => '/^[0-9]{6}$/'],
            ['ref', 'in', 'range' => ['sanmax', 'none']],
            ['repId', 'number'],
            ['registration_source', 'string']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return ArrayHelper::merge((new Profile)->attributeLabels(), [
            'responsible_pneumologist'          => Yii::t('frontend', 'Verantwoordelijke pneumoloog'),
            'username'                          => Yii::t('frontend', 'Gebruikersnaam'),
            'email'                             => Yii::t('frontend', 'E-mailadres'),
            'password'                          => Yii::t('frontend', 'Paswoord'),
            'password_repeat'                   => Yii::t('frontend', 'Herhaal paswoord'),
            'workplace_type'                    => Yii::t('frontend', 'Werkplaats'),
            'profession_declaration'            => Yii::t('frontend', 'Ja, ik verklaar een geregistreerd geneesheer of apotheker te zijn, werkzaam in België'),
        ]);
    }

    /**
     * Signs user up.
     *
     * @return User|null the saved model or null if saving fails
     */
    public function signup()
    {
        if ($this->validate()) {

            $transaction = Yii::$app->db->beginTransaction();

            // Create the user
            $user = new User([
                'username'          => $this->username,
                'email'             => $this->email,
                'password_hash'     => Yii::$app->security->generatePasswordHash($this->password),
                'auth_key'          => Yii::$app->security->generateRandomString(),
                'scope'             => User::SCOPE_FRONTEND,
                'confirmed_at'      => time()
            ]);

            if ($user->save()) {

                // Create the profile
                $profile = new Profile([
                    'user_id'                       => $user->id,
                    'name'                          => $this->name,
                    'public_email'                  => $this->email,
                    'firstname'                     => $this->firstname,
                    'salutation'                    => $this->salutation,
                    'profession'                    => $this->profession,
                    'address'                       => $this->address,
                    'city'                          => $this->city,
                    'zipcode'                       => $this->zipcode,
                    'phone'                         => $this->phone,
                    'mobile'                        => $this->mobile,
                    'workplace_type'                => (in_array($this->profession, [Profile::PROFESSION_PNEUMOLOGIST, Profile::PROFESSION_NURSE])) ? $this->workplace_type : '',
                    'workplace_name'                => (in_array($this->profession, [Profile::PROFESSION_PNEUMOLOGIST, Profile::PROFESSION_NURSE])) ? $this->workplace_name : '',
                    'riziv_number'                  => ($this->profession != Profile::PROFESSION_PHARMACIST) ? $this->riziv_number : '',
                    'doctorcode'                    => ($this->country == Profile::COUNTRY_LU) ? $this->doctorcode : '',
                    'apb_number'                    => ($this->profession == Profile::PROFESSION_PHARMACIST) ? $this->apb_number : '',
                    'responsible_pneumologist'      => ($this->profession == Profile::PROFESSION_NURSE) ? $this->responsible_pneumologist : '',
                    'language'                      => $this->language,
                    'country'                       => $this->country,
                    'registration_source'           => $this->registration_source
                ]);
                
                if ($profile->save(false)) {

                    // Attach the rep if provided
                    $rep = Rep::findOne($this->repId);
                    $member = Member::findOne($user->id);
                    if ($member && $rep) {
                        $member->link('reps', $rep);
                    }

                    $transaction->commit();
                    return $user;
                }
            }
        }

        return null;
    }

    /**
     * Sends an email to the specified email address using the information collected by this model.
     *
     * @return boolean whether the email was sent
     */
    public function sendEmail()
    {
        return Yii::$app->mailer->compose()
            ->setTo($this->to)
            ->setFrom([$this->email => "{$this->firstname} {$this->name}"])
            ->setSubject($this->subject)
            ->setHtmlBody($this->body)
            ->send();
    }

    /**
     * Save's the form email to the database
     *
     * @return  boolean
     */
    public function saveEmail()
    {
        $form = 'Registratie' . (($this->ref == 'sanmax') ? ' - Sanmax' : '');
        $email = new Email([
            'language'          => Yii::$app->language,
            'form'              => $form,
            'from'              => $this->email,
            'to'                => $this->to,
            'subject'           => $this->subject,
            'message'           => $this->body,
            'profession'        => Profile::professions()[$this->profession]
        ]);

        $rep = Rep::findOne($this->repId);
        if ($rep) {
            $email->rep = "{$rep->firstname} {$rep->name}";
        }

        return $email->save();
    }

    public function sendConfirmationEmail()
    {
        $subject = Yii::t('frontend', 'Welkom op smallairways.be');
        $body = Yii::$app->mailer->render('@common/mail/signupConfirmation', ['post' => (object) Yii::$app->request->post('SignupForm')], 'layouts/html');

        $sent = Yii::$app->mailer->compose()
                    ->setTo($this->email)
                    ->setFrom($this->to)
                    ->setSubject($subject)
                    ->setHtmlBody($body)
                    ->send();

        // Save the email
        if ($sent) {
            $form = 'Registratie' . (($this->ref == 'sanmax') ? ' - Sanmax' : '');
            $email = new Email([
                'language'          => Yii::$app->language,
                'form'              => $form,
                'from'              => $this->to,
                'to'                => $this->email,
                'subject'           => $subject,
                'message'           => $body,
                'action'            => Email::ACTION_SENT,
                'profession'        => Profile::professions()[$this->profession]
            ]);

            $rep = Rep::findOne($this->repId);
            if ($rep) {
                $email->rep = "{$rep->firstname} {$rep->name}";
            }

            $email->save();
        }

        return $sent;
    }
}
