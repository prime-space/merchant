<template>
    <div>
        <v-tabs fixed-tabs>
            <v-tab
                    v-for="(tab, key) in tabs"
                    :key="key"
                    ripple
            >
                {{ tab }}

            </v-tab>
            <v-tabs-items touchless>
                <v-tab-item>
                    <v-container>
                        <v-card>
                            <v-form ref="form" @submit.prevent="submitTimeZoneForm">
                                <v-flex xs10 offset-xs1>
                                    <v-select :error-messages="timeZoneForm.errors.timezone" name="timezone"
                                              :items="timezones" v-model="timeZoneForm.data.timezone"></v-select>
                                </v-flex>
                                <v-flex xs10 offset-xs1>
                                    <v-btn color="primary" @click="submitTimeZoneForm"><span v-lang.settingsSave></span>
                                    </v-btn>
                                </v-flex>
                            </v-form>
                        </v-card>
                    </v-container>
                </v-tab-item>
                <v-tab-item>
                    <v-container>
                        <v-card>
                            <v-form ref="form" @submit.prevent="submitPasswordChangeForm">
                                <v-flex xs10 offset-xs1>
                                    <v-text-field
                                            :type="oldPassVisible ? 'password' : 'text'"
                                            :error-messages="passwordChangeForm.errors.oldPassword"
                                            name="oldPassword"
                                            v-model="passwordChangeForm.data.oldPassword"
                                            :label="this.translate('settingsPasswordLabel')"
                                            :append-icon="oldPassVisible ? 'visibility' : 'visibility_off'"
                                            @click:append="() => (oldPassVisible = !oldPassVisible)"
                                    ></v-text-field>
                                </v-flex>
                                <v-flex xs10 offset-xs1>
                                    <v-text-field
                                            :type="newPassVisible ? 'password' : 'text'"
                                            :error-messages="passwordChangeForm.errors.newPassword"
                                            name="newPassword"
                                            v-model="passwordChangeForm.data.newPassword"
                                            :label="this.translate('settingsNewPasswordLabel')"
                                            :append-icon="newPassVisible ? 'visibility' : 'visibility_off'"
                                            @click:append="() => (newPassVisible = !newPassVisible)"
                                    ></v-text-field>
                                </v-flex>
                                <v-flex xs10 offset-xs1>
                                    <v-btn color="primary" @click="submitPasswordChangeForm"><span
                                            v-lang.settingsSave></span></v-btn>
                                </v-flex>
                            </v-form>
                        </v-card>
                    </v-container>
                </v-tab-item>
                <v-tab-item>
                    <v-container>
                        <v-card>
                            <v-form ref="apiForm" @submit.prevent="submitApiForm">
                                <v-flex xs11 offset-xs1>
                                    <v-combobox
                                            :error-messages="apiForm.errors.apiIps"
                                            name="apiIps"
                                            v-model="apiForm.data.apiIps"
                                            :hint="this.translate('settingsApiIpsHint')"
                                            :label="this.translate('settingsApiIpsLabel')"
                                            multiple
                                            persistent-hint
                                            small-chips
                                            append-icon
                                    >
                                    </v-combobox>
                                </v-flex>
                                <v-layout wrap
                                          :class="{[$vuetify.breakpoint.smAndDown ? 'column' : 'row']: true}">
                                    <v-flex xs8 offset-xs1>
                                        <v-text-field :error-messages="apiForm.errors.apiSecret" name="apiSecret"
                                                      v-model="apiForm.data.apiSecret"
                                                      :label="this.translate('settingsApiSecretLabel')"></v-text-field>
                                    </v-flex>
                                    <v-flex xs2 offset-xs1>
                                        <v-btn small color="primary" align-end right @click="generateApiSecret"><span
                                                v-lang.settingsGenerateApiSecret></span></v-btn>
                                    </v-flex>
                                </v-layout>
                                <v-flex xs11 offset-xs1>
                                    <v-text-field :error-messages="apiForm.errors.password" :type="'password'"
                                                  name="password" v-model="apiForm.data.password"
                                                  :label="this.translate('settingsPasswordLabel')"></v-text-field>
                                </v-flex>
                                <v-flex xs10 offset-xs1>
                                    <v-checkbox :error-messages="apiForm.errors.isApiEnabled" name="isApiEnabled"
                                                v-model="apiForm.data.isApiEnabled"
                                                :label="this.translate('settingsApiEnabledLabel')"></v-checkbox>
                                </v-flex>
                                <v-flex xs10 offset-xs1>
                                    <v-btn color="primary" @click="submitApiForm"><span v-lang.settingsSave></span>
                                    </v-btn>
                                </v-flex>
                            </v-form>
                        </v-card>
                    </v-container>
                </v-tab-item>
            </v-tabs-items>
        </v-tabs>
        <v-snackbar :timeout="6000" :top="'top'" v-model="snackbar">
            <span v-lang.snackSuccessMessage></span>
            <v-btn flat color="white" @click.native="snackbar = false" v-lang.snackHide></v-btn>
        </v-snackbar>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                tabs: [],
                oldPassVisible: true,
                newPassVisible: true,
                snackbar: false,
                apiForm: {data: {apiIps: '', apiSecret: '', password: '', isApiEnabled: ''}, errors: {}},
                passwordChangeForm: {data: {oldPassword: '', newPassword: ''}, errors: {}},
                timeZoneForm: {data: {timezone: ''}, errors: {}},
                timezones: [],
            }
        },
        mounted: function () {
            this.headers = [
                {text: 'ID', value: 'id'},
                {},
            ];
            this.tabs = [
                this.translate('settingsTimezoneTab'),
                this.translate('settingsPasswordChangeTab'),
                this.translate('settingsApiTab'),
            ];
            this.showFormData();
            this.showTimezonesForm();
        },
        methods: {
            showFormData() {
                this.apiForm.data.apiIps = config.userSettings.apiIps;
                this.apiForm.data.isApiEnabled = config.userSettings.isApiEnabled;
            },
            submitApiForm() {
                let url = '/settings/apiIps';
                Main.request(this.$http, this.$snack, 'post', url, this.apiForm, function (response) {
                    if (response.status === 200) {
                        config.userSettings.apiIps = this.apiForm.data.apiIps;
                        config.userSettings.isApiEnabled = this.apiForm.data.isApiEnabled;
                        this.snackbar = true;
                    }
                }.bind(this));
            },
            submitPasswordChangeForm() {
                let url = '/settings/password';
                Main.request(this.$http, this.$snack, 'post', url, this.passwordChangeForm, function (response) {
                    if (response.status === 200) {
                        this.snackbar = true;
                    }
                }.bind(this));
            },
            submitTimeZoneForm() {
                let url = '/settings/timezone';
                Main.request(this.$http, this.$snack, 'post', url, this.timeZoneForm, function (response) {
                    if (response.status === 200) {
                        this.snackbar = true;
                    }
                }.bind(this));
                config.userSettings.timezone = this.timeZoneForm.data.timezone;
            },
            generateApiSecret() {
                let chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
                this.apiForm.data.apiSecret = '';
                for (let i = 0; i < 64; i++) {
                    this.apiForm.data.apiSecret += chars.charAt(Math.floor(Math.random() * chars.length));
                }
            },
            showTimezonesForm() {
                Main.request(this.$http, this.$snack, 'get', '/settings/timezones', [], function (response) {
                    this.timeZoneForm.data.timezone = response.body.userTimezone;
                    this.timezones = response.body.timezones;
                }.bind(this));
            }
        }
    }
</script>
<style>
    .v-card > .v-form {
        padding: 16px;
    }
</style>
