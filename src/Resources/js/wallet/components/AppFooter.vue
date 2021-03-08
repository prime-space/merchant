<template>
    <footer class="footer">
        <div class="footer__container">
            <nav>
                <router-link v-for="(link,i) in footerLinks" 
                :to="link.path" 
                :key="i" 
                tag="a"
                class="footer__link">
                    {{link.text}}
                </router-link>

            </nav>

            <!--<div class="footer__social-block">
                <a href="#" class="footer__social-block__link">
                    <img class="footer__social-block__icon" src="../assets/facebook-brands.svg"/>
                </a>
                <a href="#" class="footer__social-block__link">
                    <img class="footer__social-block__icon" src="../assets/telegram-brands.svg"/>
                </a>
                <a href="#" class="footer__social-block__link">
                    <img class="footer__social-block__icon" src="../assets/twitter-brands.svg"/>
                </a>
                <a href="#" class="footer__social-block__link">
                    <img class="footer__social-block__icon" src="../assets/instagram-brands.svg"/>
                </a>
            </div>-->

            <div class="footer__copyright">
                <span>{{ config.siteName }} © {{ config.year }}</span>
            </div>
        </div>
    </footer>
</template>
<script>


export default {
   data(){
       return{
           config: config,
           footerLinks:[
               /*{
                    text:'Безопасность',
                    path:'/'
                },
                {
                    text:'Разработчикам',
                    path:'/operations'
                },
                {
                    text:'Юридическая Информация',
                    path:'/'
                },
                {
                    text:'Мобильная Версия',
                    path:'/'
                },*/
           ]
       }
   }
}


</script>
<style lang="scss" scoped>
    .footer__container{
        width: 1110px;
        margin:30px auto 33px;
        display: flex;
        justify-content: space-between;
        

        @media screen and (max-width:1170px){
            margin: 0 auto;
            padding: 30px 0;
            width: 100%;
            display: block;
            
        }
    }

    .footer__link{
        font-family: 'Roboto',sans-serif;
        font-size: 14px;
        color: #B9BBBE;
        font-weight: bold;
        text-decoration: none;
        margin-left: 12px;

        img{
            width: 30px;
            height: 30px;
            color: red;
        }

        &::first-letter{
            text-transform: capitalize;
        }

        &:hover{
            color: #5B5F63;
        }

        @media screen and (max-width:1170px){
            margin: 10px 0;
            display: block;
            text-align: center;
        }
    }


    .footer__social-block{
        margin-top: 20px;
        display: flex;
        justify-content: center;
        color: #B9BBBE;

        a{
            text-decoration: none;
            color: #B9BBBE;

            &:hover{
                color: #5B5F63;
            }
        }

        @media screen and (min-width: 1170px){
            display: none;
        }

    }

    .footer__social-block__link{
        margin: 0 15px;
    }

    .footer__social-block__icon{
        width: 20px;
        height: 20px;
        color: #B9BBBE;

        &:hover{
            color: #5B5F63;
        }
    }

    .footer__copyright{
        font-family: 'Roboto',sans-serif;
        font-size: 14px;
        font-weight: bold;
        color: #B9BBBE;
        
        @media screen and (max-width:1170px){
            text-align: center;
            margin-top: 20px;
        }
    }
</style>
