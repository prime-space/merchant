<template>
    <div>
        <TicketMessage v-for="message in messages" :key="message.id"
                        :text="message.text"
                        :author="message.author"
                        :createdTs="message.createdTs"
                        :isAnswer="message.isAnswer"
        ></TicketMessage>

        <v-container>
            <v-form ref="messageForm" @submit.prevent="submitApiForm">
                <v-flex xs10 offset-xs1>
                    <v-textarea outline :error-messages="messageForm.errors.message" name="message" v-model="messageForm.data.message" :label="this.translate('ticketMessageLabel')"></v-textarea>
                </v-flex>
                <v-flex xs10 offset-xs1>
                    <v-btn color="primary" @click="submitMessageForm" :disabled="messageForm.submitting">{{this.translate('ticketSendMessage')}}</v-btn>
                </v-flex>
            </v-form>
        </v-container>
    </div>
</template>

<script>
    import TicketMessage from './components/TicketMessage';
    export default {
        components: {
            TicketMessage,
        },
        data() {
            return {
                messages: [],
                messageForm: {data: {message: '', ticketId: null}, errors: {}, submitting: false},
            }
        },
        mounted: function () {
            this.showMessages();
        },
        methods: {
            showMessages() {
                let url = `/private/ticket/${this.$route.params.id}`;
                Main.request(this.$http, this.$snack, 'get', url, [], function (response) {
                    this.messages = response.body.messages;
                    this.messages.forEach(function(message) {
                        message.createdTs = Main.convertTimeZone(message.createdTs);
                    }.bind(this));
                    this.$store.commit('changeTitle', `Поддержка - ${response.body.ticketSubject}`);
                }.bind(this));
            },
            submitMessageForm() {
                this.messageForm.data.ticketId = this.$route.params.id;
                this.messageForm.submitting = true;
                let url = '/private/ticket/message';
                Main.request(this.$http, this.$snack, 'post', url, this.messageForm, function (response) {
                    this.showMessages();
                    this.messageForm.data.message = '';
                }.bind(this));
            }
        }
    }
</script>
