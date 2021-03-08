<template>
    <div>
        <confirm-dialog />
        <v-data-table :headers="headers" :items="tickets" hide-actions class="elevation-1" :loading="loading">
            <v-progress-linear slot="progress" color="blue" indeterminate></v-progress-linear>
            <template slot="items" slot-scope="props">
                <tr @click="$router.push(`/ticket/${props.item.id}`)" :style="{ cursor: 'pointer'}">
                <td>{{ props.item.id }}</td>
                <td>{{ props.item.subject }}</td>
                <td>{{ props.item.lastMessageTs }}</td>
                <td class="justify-center layout px-0">
                    <div v-if="props.item.hasUnreadMessage">
                        <v-chip>{{translate('supportNewMessage')}}</v-chip>
                    </div>
                </td>
                </tr>
            </template>
        </v-data-table>
        <v-btn color="primary" dark slot="activator" @click="openTicketForm()"><span v-lang.supportCreateTicket></span></v-btn>
        <v-dialog v-model="ticketForm.dialog" persistent max-width="500px">
            <v-form ref="form" @submit.prevent="submitTicketForm">
                <v-card>
                    <v-card-title>
                        <span class="headline" v-lang.supportNewTicket></span>
                    </v-card-title>
                    <v-card-text>
                        <v-container grid-list-md>
                            <v-layout wrap>
                                <v-flex xs12>
                                    <v-text-field :error-messages="ticketForm.errors.theme" name="theme" v-model="ticketForm.data.theme" :label="this.translate('supportThemeLabel')"></v-text-field>
                                </v-flex>
                                <v-flex xs12>
                                    <v-textarea outline :error-messages="ticketForm.errors.message" name="message" v-model="ticketForm.data.message" :label="this.translate('supportMessageLabel')"></v-textarea>
                                </v-flex>
                            </v-layout>
                        </v-container>
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn color="blue darken-1" flat @click.native="ticketForm.dialog = false"><span v-lang.supportFormClose></span></v-btn>
                        <v-btn type="submit" name="save" color="blue darken-1"flat :disabled="ticketForm.submitting"><span v-lang.supportCreateButton></span></v-btn>
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                headers: [],
                tickets: [],
                loading: false,
                ticketForm: {editId: null, data: {}, errors: {}, submitting: false, dialog: false},
            }
        },
        mounted: function () {
            this.headers = [
                {text: '#', value: 'id'},
                {text: this.translate('supportTableHeadTheme'), value: 'theme'},
                {text: this.translate('supportTableHeadDate'), value: 'date'},
                {value: 'tags'},
            ];
            this.showTickets();
        },
        methods: {
            showTickets() {
                this.loading = true;
                Main.request(this.$http, this.$snack, 'get', '/private/tickets', [], function (response) {
                    this.loading = false;
                    this.tickets = response.body;
                    this.tickets.forEach(function(ticket) {
                        ticket.lastMessageTs = Main.convertTimeZone(ticket.lastMessageTs);
                    }.bind(this));
                }.bind(this));
            },
            openTicketForm() {
                this.ticketForm.data = {message: '', theme: ''};
                this.ticketForm.errors = {};
                this.ticketForm.dialog = true;
            },
            submitTicketForm(submitEvent) {
                this.ticketForm.submitting = true;
                let url = '/private/ticket';
                Main.request(this.$http, this.$snack, 'post', url, this.ticketForm, function (response) {
                    this.ticketForm.dialog = false;
                    this.showTickets();
                    this.$router.push(`/ticket/${response.body.id}`);
                }.bind(this));
            },
        }
    }
</script>

<style>
</style>
