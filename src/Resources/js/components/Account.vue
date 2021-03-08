<template>
    <v-flex xs12>
        <v-card color="blue darken-2" class="white--text">
            <v-card-title primary-title>
                <div class="account__content">
                    <div class="headline">{{balance}}<span v-if="sign"> {{sign}}</span></div>
                    <div><span v-lang.balance></span><span v-if="id" class="account__id">#{{id}}</span></div>
                </div>
            </v-card-title>
        </v-card>
    </v-flex>
</template>

<script>
    export default {
        props: ['id', 'balance', 'sign'],
    }
</script>

<style>
    .account__content {
        width: 100%;
    }
    .account__id {
        float: right;
    }
</style>
