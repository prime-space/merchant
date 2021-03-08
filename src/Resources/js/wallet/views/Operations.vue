<template>
    <div>
        <div class="transactions">
            <transaction
                    v-for="(transaction,i) in transactions"
                    :key="i"
                    :number="i"
                    :data="transaction"
                    class="transaction"
            />
        </div>
        <list-message v-if="showHaveNoOperationsMessage">Пока еще нет ни одной операции</list-message>
        <btn v-if="showLoadMoreButton"
             :handler="loadMore"
             class="transactions__loadMore"
             ref="loadMoreButton"
             width="100%"
             hoveroff
        >показать еще
        </btn>
    </div>
</template>
<script>
    import Transaction from './../components/Transaction'
    import Btn from './../components/Button'
    import ListMessage from './../components/ListMessage'

    export default {
        components: {
            Transaction,
            Btn,
            ListMessage,
        },
        mounted: function () {
            this.$refs.loadMoreButton.$el.click();
        },
        methods: {
            loadMore(after) {
                let url = '/private/transactions/' + this.lastTransactionIdInList;
                Main.request(this.$http, this.$snack, 'get', url, [], function (response) {
                    if (response.body.transactions.length > 0) {
                        response.body.transactions.forEach(function (transaction) {
                            this.transactions.push(transaction);
                        }.bind(this));
                        this.lastTransactionIdInList = response.body.lastTransactionIdInList;
                    }
                    if (response.body.transactions.length < 5) {
                        this.showLoadMoreButton = false;
                        if (this.lastTransactionIdInList === 0) {
                            this.showHaveNoOperationsMessage = true;
                        }
                    }
                    after();
                }.bind(this), function () {
                    after();
                }.bind(this));
            }
        },
        data() {
            return {
                showHaveNoOperationsMessage: false,
                showLoadMoreButton: true,
                transactions: [],
                lastTransactionIdInList: 0,
            }
        },
    }
</script>
<style lang="scss" scoped>
    .transactions__loadMore {
        margin: 15px auto 0;
    }
</style>
