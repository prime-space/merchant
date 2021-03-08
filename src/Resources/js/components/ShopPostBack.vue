<template>
    <div>
        <v-container>
            <v-card>
                <v-form ref="form" @submit.prevent="submit">
                    <v-card-title>
                        <v-flex xs12>
                            <v-checkbox :error-messages="form.errors.isPostbackEnabled" name="isPostbackEnabled"
                                        v-model="form.data.isPostbackEnabled"
                                        :label="this.translate('shopPostBackEnabledLabel')"></v-checkbox>
                        </v-flex>
                        <v-flex xs12>
                            <v-text-field :error-messages="form.errors.postbackUrl" name="postbackUrl"
                                          v-model="form.data.postbackUrl"
                                          :label="this.translate('shopPostBackUrl')"></v-text-field>
                        </v-flex>
                    </v-card-title>
                    <v-card-actions>
                        <v-btn type="submit" color="primary" :disabled="form.submitting">
                            <span v-lang.shopPostBackSave></span>
                        </v-btn>
                        <v-btn flat color="primary" href="/doc#/merchant/postback" target="_blank" v-lang.shopPostBackDoc></v-btn>
                    </v-card-actions>
                </v-form>
            </v-card>
        </v-container>
    </div>
</template>

<script>
    export default {
        props: ['shop'],
        data() {
            return {
                form: null,
            }
        },
        created() {
            this.form = Main.initForm({isPostbackEnabled: this.shop.isPostbackEnabled, postbackUrl: this.shop.postbackUrl});
        },
        methods: {
            submit() {
                this.form.submitting = true;
                let url = '/private/shop/' + this.shop.id + '/postback';
                Main.request(this.$http, this.$snack, 'post', url, this.form, function (response) {
                    this.$snack.success({text: this.translate('snackSuccessMessage'), button: 'закрыть'});
                }.bind(this));
            },
        }
    }
</script>

<style>
</style>
