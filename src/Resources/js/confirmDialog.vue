<template>
    <v-layout v-if="confirmer.active" row justify-center>
        <v-dialog :value="confirmer.active" persistent max-width="290">
            <v-card>
                <v-card-title class="headline">{{confirmer.title}}</v-card-title>
                <v-card-text v-if="confirmer.body">{{confirmer.body}}</v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="blue darken-1" flat @click.native="cancel">Отмена</v-btn>
                    <v-btn color="blue darken-1" flat @click.native="confirm">Подтвердить</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-layout>
</template>

<script>
    export default {
        data () {
            return {}
        },
        computed: {
            confirmer() {
                return this.$store.state.confirmer
            }
        },
        methods: {
            confirm() {
                this.confirmer.resolve(true)
                this.$store.commit('confirmer/DEACTIVATE')
            },
            cancel() {
                this.confirmer.resolve(false)
                this.$store.commit('confirmer/DEACTIVATE')
            }
        }
    }
</script>

<style>

</style>
