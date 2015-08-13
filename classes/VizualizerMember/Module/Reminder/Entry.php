<?php

/**
 * Copyright (C) 2012 Vizualizer All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @author    Naohisa Minagawa <info@vizualizer.jp>
 * @copyright Copyright (c) 2010, Vizualizer
 * @license http://www.apache.org/licenses/LICENSE-2.0.html Apache License, Version 2.0
 * @since PHP 5.3
 * @version   1.0.0
 */

/**
 * リマインダーエントリーのデータを保存する。
 *
 * @package VizualizerMember
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerMember_Module_Reminder_Entry extends Vizualizer_Plugin_Module_Save
{

    function execute($params)
    {
        $post = Vizualizer::request();

        // 受け取ったメールアドレスに対応する顧客を検索する。
        $loader = new Vizualizer_Plugin("Member");
        $model = $loader->loadModel("Customer");
        $model->findBy(array("email" => $post[$params->get("key", "email")]));

        // 該当する顧客がない場合はエラー
        if (!($model->customer_id > 0)) {
            throw new Vizualizer_Exception_Invalid($params->get("key", "email"), $params->get("value", "メールアドレス").$params->get("suffix", "が正しくありません。"));
        }

        $connection = Vizualizer_Database_Factory::begin("member");
        try {
            // 顧客情報を取得できた場合は、リマインダー用のエントリーデータを作成する。
            $reminder = $loader->loadModel("ReminderEntry");
            $reminder->customer_id = $model->customer_id;
            $reminder->reminder_key = Vizualizer_Data_UniqueCode::get();
            $reminder->auth_key = Vizualizer_Data_UniqueCode::get();
            $reminder->save();
            // エラーが無かった場合、処理をコミットする。
            Vizualizer_Database_Factory::commit($connection);

            // リマインダーメールの内容を作成
            $title = Vizualizer_Configure::get("reminder_mail_title");
            $templateName = Vizualizer_Configure::get("reminder_mail_template");
            $attr = Vizualizer::attr();
            $template = $attr["template"];
            if(!empty($template) && $reminder->reminder_entry_id > 0){
                $customer = $reminder->customer();
                $template->assign("reminder", $reminder->toArray());
                $body = $template->fetch($templateName.".txt");

                // ショップの情報を取得
                $loader = new Vizualizer_Plugin("admin");
                $company = $loader->loadModel("Company");
                if (Vizualizer_Configure::get("delegate_company") > 0) {
                    $company->findBy(array("company_id" => Vizualizer_Configure::get("delegate_company")));
                } else {
                    $company->findBy(array());
                }

                // 購入者にメール送信
                $mail = new Vizualizer_Sendmail();
                $mail->setFrom($company->email);
                $mail->setTo($customer->email);
                $mail->setSubject($title);
                $mail->addBody($body);
                $mail->send();

                // リマインダーのオブジェクトを結果として返す。
                $attr = Vizualizer::attr();
                $attr["reminder"] = $reminder->toArray();
            }
        } catch (Exception $e) {
            Vizualizer_Database_Factory::rollback($connection);
            throw new Vizualizer_Exception_Database($e);
        }
    }
}
