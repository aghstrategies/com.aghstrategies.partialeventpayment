# com.aghstrategies.partialeventpayment

1. Adds two fields to the Price Option Form:
 - An installments field checkbox
 - A Select for "Which full price option does this field refer to?"  These fields are used to create price fields with an option to pay the whole amount up front or put down a deposit. See Screenshot below:  
![Screenshot of Price Option custom fields](./img/installmentsField.png)  

2. On event Registration forms using price sets for which partial payments have been configured (on the price options), creates a partially paid registration if the partial payment option is selected.

Abstracted from [warriorpartialevents](https://git.aghstrategies.com/clients/weekend-warriors/warriorpartialevents)
