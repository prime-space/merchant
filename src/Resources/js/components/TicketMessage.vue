<template>
    <div class="ticket-message" :class="{'ticket-message__answer': isAnswer}">
        <div>
            <div class="ticket-message__text">{{ text }}</div>
        </div>
        <div class="ticket-message__info">
            <div>{{ author }}</div>
            <div class="ticket-message__date">{{ createdTs }}</div>
        </div>
    </div>
</template>

<script>
    export default {
        props: ['text', 'author', 'createdTs', 'isAnswer']
    }
</script>

<style>
    .ticket-message {
        overflow-wrap: break-word;
        display: table;
        padding: 5px 10px 2px;
        margin-bottom: 10px;
        border-radius: 10px;
        background-color: #c3e7fc;
        white-space: pre-wrap;
    }
    .ticket-message__answer {
        background-color: #ececec;
    }
    .ticket-message>div {
        display: table-row;
    }
    .ticket-message>div>div {
        display: inline-block;
    }
    .ticket-message__info {
        color: #bbb;
        font-size: 90%;
    }
    .ticket-message__date {
        padding-left: 10px;
        float: right;
    }
    .ticket-message__text {
        display: inline-block;
        word-break: normal;
    }
</style>
