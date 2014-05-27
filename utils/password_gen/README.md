Password Generator
==================

Basics
======
Generate a list of random passwords given parameters:
 * `num` (int): the number of passwords to generate
 * `size` (int): the length of each password
 * `base` (string): the set of characters to use in passwords
 * `unique` (bool): if set `True`, all passwords generated are unique

Pairing
=======
To generate a list of username-password pairs, provide
a file named `user.lst` in which usernames are written line by line (the
line delimiter, i.e., `\r` and `\n`, will be removed automatically).

For example, 

```
bu1
xb
tom_riddle
```

Contact
==========
Xiangyu Bu <xybu92@live.com>